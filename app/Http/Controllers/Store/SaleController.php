<?php

namespace App\Http\Controllers\Store;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CompleteSaleRequest;
use App\Http\Requests\Store\SalePaymentRequest;
use App\Http\Requests\Store\SaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\InvoiceService;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly InvoiceService $invoices,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->with(['customer', 'creator'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('invoice_no', 'like', $term)
                        ->orWhere('walk_in_name', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('store.sales.index', compact('sales'));
    }

    public function create(): View
    {
        $this->authorize('create', Sale::class);

        return view('store.sales.create', $this->formData());
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        $sale = $this->saleService->create(
            $request->safe()->except('items', 'payment_amount', 'payment_method'),
            $request->validated('items'),
            $request->user()->id,
        );

        if ($request->boolean('complete')) {
            if (! $request->user()->canConfirmTill()) {
                return redirect()->route('store.sales.show', $sale)
                    ->with('success', 'Draft sale saved. The cashier records the payment once on Daily Accounts.');
            }

            try {
                return $this->takeSalePayment(
                    $sale,
                    [
                        'amount' => $request->input('payment_amount', 0),
                        'method' => $request->input('payment_method', 'cash'),
                        'payment_date' => $request->input('sale_date'),
                    ],
                    $request->user(),
                );
            } catch (RuntimeException $e) {
                return redirect()->route('store.sales.show', $sale)->with('error', $e->getMessage());
            }
        }

        return redirect()->route('store.sales.show', $sale)->with('success', 'Draft sale saved.');
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['customer', 'creator', 'items.product.unit', 'payments.receiver', 'payments.financialTransaction']);

        return view('store.sales.show', compact('sale'));
    }

    public function edit(Sale $sale): View
    {
        $this->authorize('update', $sale);

        $sale->load('items');

        return view('store.sales.edit', [...$this->formData(), 'sale' => $sale]);
    }

    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        try {
            $this->saleService->update(
                $sale,
                $request->safe()->except('items', 'payment_amount', 'payment_method'),
                $request->validated('items'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('store.sales.show', $sale)->with('success', 'Sale updated.');
    }

    public function complete(CompleteSaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('complete', $sale);

        try {
            return $this->takeSalePayment($sale, [
                'amount' => $request->input('payment_amount', 0),
                'method' => $request->input('payment_method', 'cash'),
                'payment_date' => $sale->sale_date,
            ], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pay(SalePaymentRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('pay', $sale);

        try {
            if ((string) $request->input('payment_method') === PaymentMethod::Credit->value) {
                throw new RuntimeException('Record an actual payment. Credit does not collect money.');
            }

            return $this->takeSalePayment($sale, [
                'amount' => $request->input('amount'),
                'method' => $request->input('payment_method'),
                'payment_date' => $request->input('payment_date', now()->toDateString()),
                'reference' => $request->input('reference'),
                'notes' => $request->input('notes'),
            ], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        try {
            $this->saleService->cancel($sale);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('store.sales.index')->with('success', 'Draft sale cancelled.');
    }

    public function invoice(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        abort_unless($sale->isCompleted(), 404);

        return $this->invoices->stream($sale);
    }

    public function invoiceDownload(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        abort_unless($sale->isCompleted(), 404);

        return $this->invoices->download($sale);
    }

    public function bill(Sale $sale): View
    {
        $this->authorize('view', $sale);

        abort_unless($sale->isCompleted(), 404);

        $sale->load(['items.product.unit', 'customer', 'payments.receiver']);

        return view('store.sales.bill', [
            'sale' => $sale,
            'company' => $this->invoices->company(),
        ]);
    }

    public function print(Request $request, Sale $sale): View
    {
        $this->authorize('view', $sale);

        abort_unless($sale->isCompleted(), 404);

        $sale->load(['items.product.unit', 'customer', 'payments']);

        return view('store.sales.print', [
            'sale' => $sale,
            'company' => $this->invoices->company(),
            'goToNewSale' => $request->boolean('next'),
        ]);
    }

    private function takeSalePayment(Sale $sale, array $payment, $user): RedirectResponse
    {
        if (! $user->canConfirmTill()) {
            return redirect()->route('store.sales.show', $sale)
                ->with('error', 'The cashier records this payment on Daily Accounts.');
        }

        $method = (string) ($payment['method'] ?? 'cash');

        if ($method === PaymentMethod::Credit->value) {
            $this->saleService->complete($sale, $payment, $user->id);

            return $this->redirectAfterComplete($sale->fresh());
        }

        if ($sale->isDraft()) {
            $this->saleService->complete($sale, $payment, $user->id);
        } else {
            $this->saleService->recordPayment($sale, [
                'amount' => $payment['amount'] ?? 0,
                'payment_method' => $method,
                'payment_date' => $payment['payment_date'] ?? now()->toDateString(),
                'reference' => $payment['reference'] ?? null,
                'notes' => $payment['notes'] ?? null,
            ], $user->id);
        }

        if ($sale->fresh()->isCompleted()) {
            return $this->redirectAfterComplete($sale);
        }

        return back()->with('success', 'Payment recorded in Daily Accounts.');
    }

    private function redirectAfterComplete(Sale $sale): RedirectResponse
    {
        $invoiceNo = $sale->fresh()->invoice_no;

        return redirect()
            ->route('store.sales.print', ['sale' => $sale, 'next' => 1])
            ->with('success', "Sale {$invoiceNo} completed. Print the bill, then start the next sale.");
    }

    private function formData(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'products' => Product::query()->active()->with('unit')->orderBy('name')->get(),
        ];
    }
}
