<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\DailyAccountEntryRequest;
use App\Http\Requests\Cashier\DailyAccountOpeningRequest;
use App\Models\DailyAccountEntry;
use App\Models\Project;
use App\Models\Worker;
use App\Services\DailyAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyAccountController extends Controller
{
    public function __construct(private readonly DailyAccountService $accounts) {}

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

        return view('cashier.daily-accounts', [
            'filters' => $filters,
            'day' => $day,
            'totals' => $totals,
            'rows' => $rows,
            'onlyDate' => $onlyDate,
            'filteredIncome' => $filteredIncome,
            'filteredExpense' => $filteredExpense,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name']),
            'types' => DailyAccountType::cases(),
            'categories' => DailyAccountCategory::cases(),
            'methods' => array_values(array_filter(
                PaymentMethod::cases(),
                fn (PaymentMethod $method) => $method !== PaymentMethod::Credit,
            )),
        ]);
    }

    public function store(DailyAccountEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = DailyAccountType::from($data['type']);
        $amount = round((float) $data['amount'], 2);

        $this->accounts->post([
            'occurred_on' => $data['occurred_on'],
            'type' => $type,
            'category' => $data['category'],
            'description' => $data['description'],
            'project_id' => $data['project_id'] ?? null,
            'worker_id' => $data['worker_id'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'method' => $data['method'] ?? null,
            'income' => $type->isIncome() ? $amount : 0,
            'expense' => $type->isIncome() ? 0 : $amount,
            'is_manual' => true,
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('cashier.daily-accounts.index', ['from' => $data['occurred_on'], 'to' => $data['occurred_on']])
            ->with('success', 'Transaction recorded in daily accounts.');
    }

    public function destroy(Request $request, DailyAccountEntry $entry): RedirectResponse
    {
        abort_unless($request->user()?->canManageDailyAccounts(), 403);

        if (! $entry->is_manual) {
            return back()->with('error', 'This row was posted from another page. Reverse it there instead of deleting it here.');
        }

        $date = $entry->occurred_on->toDateString();
        $entry->delete();

        return redirect()
            ->route('cashier.daily-accounts.index', ['from' => $date, 'to' => $date])
            ->with('success', 'Manual transaction removed.');
    }

    public function updateOpening(DailyAccountOpeningRequest $request): RedirectResponse
    {
        $this->accounts->setOpening(
            $request->input('business_date'),
            (float) $request->input('opening_balance'),
            $request->user()->id,
            $request->input('notes'),
        );

        return redirect()
            ->route('cashier.daily-accounts.index', [
                'from' => $request->input('business_date'),
                'to' => $request->input('business_date'),
            ])
            ->with('success', 'Opening balance saved. Closing balance has been recalculated.');
    }
}
