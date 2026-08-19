<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = Purchase::query()
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('store.purchases.index', compact('purchases'));
    }

    public function create(): View
    {
        $this->authorize('create', Purchase::class);

        return view('store.purchases.create', $this->formData());
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchaseService->create(
            $request->safe()->except('items'),
            $request->validated('items'),
            $request->user()->id,
        );

        if ($request->boolean('complete')) {
            return redirect()->route('store.purchases.show', $purchase)
                ->with('success', 'Draft purchase saved. The cashier records the payment once on Daily Accounts, then stock updates.');
        }

        return redirect()->route('store.purchases.show', $purchase)
            ->with('success', "Draft purchase {$purchase->reference_no} saved.");
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);

        $purchase->load(['supplier', 'creator', 'items.product.unit', 'financialTransaction']);

        return view('store.purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase): View
    {
        $this->authorize('update', $purchase);

        $purchase->load('items');

        return view('store.purchases.edit', [...$this->formData(), 'purchase' => $purchase]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        try {
            $this->purchaseService->update(
                $purchase,
                $request->safe()->except('items'),
                $request->validated('items'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('store.purchases.show', $purchase)->with('success', 'Purchase updated.');
    }

    public function complete(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('complete', $purchase);

        return redirect()->route('store.purchases.show', $purchase)
            ->with('error', 'The cashier records this payment on Daily Accounts. Stock will update from that transaction.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);

        try {
            $this->purchaseService->cancel($purchase);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('store.purchases.index')->with('success', 'Purchase cancelled.');
    }

    private function formData(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(),
            'products' => Product::query()->active()->with('unit')->orderBy('name')->get(['id', 'name', 'sku', 'purchase_price', 'unit_id']),
        ];
    }
}
