<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CloseDailyAccountDayRequest;
use App\Http\Requests\Cashier\ConfirmCashierRequestRequest;
use App\Http\Requests\Cashier\DailyAccountEntryRequest;
use App\Http\Requests\Cashier\DailyAccountOpeningRequest;
use App\Models\CashierRequest;
use App\Models\DailyAccountEntry;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Worker;
use App\Services\CashierRequestService;
use App\Services\CashierTransactionService;
use App\Services\DailyAccountReportService;
use App\Services\DailyAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class DailyAccountController extends Controller
{
    public function __construct(
        private readonly DailyAccountService $accounts,
        private readonly DailyAccountReportService $reports,
        private readonly CashierTransactionService $transactions,
        private readonly CashierRequestService $cashierRequests,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->canManageDailyAccounts(), 403);

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', $from);
        $filters = [
            'from' => $from,
            'to' => $to,
            'project_id' => $request->input('project_id'),
            'worker_id' => $request->input('worker_id'),
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'direction' => $request->input('direction'),
            'method' => $request->input('method'),
        ];

        $day = $this->accounts->dayFor($from, $request->user()->id);
        $day->loadMissing('closer');
        $totals = $this->accounts->totalsFor($from);
        $entries = $this->accounts->entries($filters);

        $onlyDate = $from === $to
            && empty($filters['project_id'])
            && empty($filters['worker_id'])
            && empty($filters['type'])
            && empty($filters['category'])
            && empty($filters['direction'])
            && empty($filters['method']);

        $running = $onlyDate ? $totals['opening'] : 0.0;
        $rows = $entries->map(function (DailyAccountEntry $entry) use (&$running) {
            $running = round($running + $entry->net(), 2);

            return ['entry' => $entry, 'balance' => $running];
        });

        $filteredIncome = round((float) $entries->sum('income'), 2);
        $filteredExpense = round((float) $entries->sum('expense'), 2);
        $pending = CashierRequest::query()
            ->pending()
            ->with(['project', 'worker', 'requester', 'subject'])
            ->latest()
            ->get();

        $openSales = Sale::query()
            ->with('customer')
            ->where(function ($query) {
                $query->where('status', SaleStatus::Draft)
                    ->orWhere('balance', '>', 0);
            })
            ->latest('id')
            ->limit(80)
            ->get(['id', 'invoice_no', 'walk_in_name', 'customer_id', 'total', 'balance', 'status']);

        $draftPurchases = Purchase::query()
            ->with('supplier')
            ->where('status', PurchaseStatus::Draft)
            ->latest('id')
            ->limit(80)
            ->get(['id', 'reference_no', 'supplier_id', 'total', 'status']);

        return view('cashier.daily-accounts', [
            'filters' => $filters,
            'day' => $day,
            'totals' => $totals,
            'rows' => $rows,
            'onlyDate' => $onlyDate,
            'filteredIncome' => $filteredIncome,
            'filteredExpense' => $filteredExpense,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'project_code']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'worker_code']),
            'openSales' => $openSales,
            'draftPurchases' => $draftPurchases,
            'expenseCategories' => ExpenseCategory::manualCases(),
            'types' => DailyAccountType::cases(),
            'incomeTypes' => DailyAccountType::incomeCases(),
            'expenseTypes' => DailyAccountType::expenseCases(),
            'categories' => DailyAccountCategory::cases(),
            'methods' => array_values(array_filter(
                PaymentMethod::cases(),
                fn (PaymentMethod $method) => $method !== PaymentMethod::Credit,
            )),
            'pending' => $pending,
            'canRecord' => $request->user()->canConfirmTill() && ! $day->isClosed(),
            'canClose' => $request->user()->canConfirmTill() && $onlyDate && ! $day->isClosed(),
            'canReopen' => $request->user()->hasRole('admin') && $onlyDate && $day->isClosed(),
        ]);
    }

    public function print(Request $request): View
    {
        abort_unless($request->user()?->canManageDailyAccounts(), 403);

        $date = $request->input('date', now()->toDateString());

        return view('cashier.daily-accounts-print', $this->reports->report($date));
    }

    public function pdf(Request $request): Response
    {
        abort_unless($request->user()?->canManageDailyAccounts(), 403);

        $date = $request->input('date', now()->toDateString());

        return $this->reports->download($date);
    }

    public function close(CloseDailyAccountDayRequest $request): RedirectResponse
    {
        try {
            $day = $this->accounts->closeDay(
                $request->validated('business_date'),
                $request->user(),
                $request->filled('counted_cash') ? (float) $request->validated('counted_cash') : null,
                $request->validated('close_notes'),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $date = $day->business_date->toDateString();

        return redirect()
            ->route('cashier.daily-accounts.print', ['date' => $date])
            ->with('success', 'Day closed. Print or save the PDF for the till check and paper backup.');
    }

    public function reopen(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $date = $request->input('business_date', now()->toDateString());

        try {
            $this->accounts->reopenDay($date, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('cashier.daily-accounts.index', ['from' => $date, 'to' => $date])
            ->with('success', 'Day reopened. New transactions can be recorded again.');
    }

    public function confirm(ConfirmCashierRequestRequest $request, CashierRequest $cashierRequest): RedirectResponse
    {
        try {
            $this->cashierRequests->confirm($cashierRequest, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Payment recorded. Daily accounts and the related page have been updated.');
    }

    public function reject(Request $request, CashierRequest $cashierRequest): RedirectResponse
    {
        abort_unless($request->user()?->canConfirmTill(), 403);

        try {
            $this->cashierRequests->reject($cashierRequest, $request->user(), $request->input('rejection_reason'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Request rejected. No money was recorded.');
    }

    public function store(DailyAccountEntryRequest $request): RedirectResponse
    {
        try {
            $entry = $this->transactions->record(
                DailyAccountType::from($request->validated('type')),
                $request->validated(),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        $date = $entry->occurred_on->toDateString();

        return redirect()
            ->route('cashier.daily-accounts.index', ['from' => $date, 'to' => $date])
            ->with('success', "Recorded {$entry->transaction_no}. Related pages have been updated from this transaction.");
    }

    public function destroy(Request $request, DailyAccountEntry $entry): RedirectResponse
    {
        abort_unless($request->user()?->canConfirmTill(), 403);

        if (! $entry->is_manual) {
            return back()->with('error', 'This row is the cash book copy of a cashier transaction. Reverse the sale, purchase, wage, or project payment instead of deleting it here.');
        }

        try {
            $this->accounts->assertDayOpen($entry->occurred_on->toDateString());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $date = $entry->occurred_on->toDateString();
        $entry->delete();

        return redirect()
            ->route('cashier.daily-accounts.index', ['from' => $date, 'to' => $date])
            ->with('success', 'Manual transaction removed.');
    }

    public function updateOpening(DailyAccountOpeningRequest $request): RedirectResponse
    {
        try {
            $this->accounts->setOpening(
                $request->input('business_date'),
                (float) $request->input('opening_balance'),
                $request->user()->id,
                $request->input('notes'),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('cashier.daily-accounts.index', [
                'from' => $request->input('business_date'),
                'to' => $request->input('business_date'),
            ])
            ->with('success', 'Opening balance saved. Closing balance has been recalculated.');
    }
}
