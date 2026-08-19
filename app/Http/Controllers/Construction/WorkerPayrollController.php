<?php

namespace App\Http\Controllers\Construction;

use App\Enums\CashierRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\SettleWorkerWeekRequest;
use App\Http\Requests\Construction\WorkerAdvanceRequest;
use App\Http\Requests\Construction\WorkerWorkDayRequest;
use App\Models\CashierRequest;
use App\Models\Project;
use App\Models\Worker;
use App\Models\WorkerPayrollWeek;
use App\Models\WorkerWorkDay;
use App\Services\CashierRequestService;
use App\Services\WorkerPayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

class WorkerPayrollController extends Controller
{
    public function __construct(
        private readonly WorkerPayrollService $payroll,
        private readonly CashierRequestService $cashier,
    ) {}

    /**
     * Payday overview: every active worker for one week, so the shop can settle
     * wages from a single screen on Saturday.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Worker::class);

        $weekStart = $this->payroll->weekStartFor(
            $request->filled('week') ? $request->string('week')->toString() : now(),
        );

        $workers = Worker::query()
            ->active()
            ->with(['payrollWeeks' => fn ($query) => $query->whereDate('week_start', $weekStart)->with('payments')])
            ->orderBy('name')
            ->get();

        return view('construction.workers.payroll-index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->endOfWeek(WorkerPayrollService::PAYDAY),
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'rows' => $workers->map(fn (Worker $worker) => [
                'worker' => $worker,
                'week' => $worker->payrollWeeks->first(),
                'debt' => $worker->debtBalance(),
            ]),
        ]);
    }

    /**
     * One pay week for one worker: the work sheet, the advances, and what is
     * still owed on Saturday.
     */
    public function show(Request $request, Worker $worker): View
    {
        $this->authorize('view', $worker);

        $week = $this->payroll->weekFor(
            $worker,
            $request->filled('week') ? $request->string('week')->toString() : now(),
            $request->user()->id,
        );

        $week->load([
            'payments.project',
            'payments.recorder',
            'payments.financialTransaction',
            'workDays.project',
            'settler',
        ]);

        return view('construction.workers.payroll', [
            'worker' => $worker,
            'week' => $week,
            'debtBalance' => $worker->debtBalance(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'previousWeek' => $week->week_start->copy()->subWeek()->toDateString(),
            'nextWeek' => $week->week_start->copy()->addWeek()->toDateString(),
            'history' => $worker->payrollWeeks()->with('payments')->limit(12)->get(),
            'pendingCashier' => CashierRequest::query()
                ->pending()
                ->where('worker_id', $worker->id)
                ->latest()
                ->get(),
        ]);
    }

    public function storeAdvance(WorkerAdvanceRequest $request, Worker $worker): RedirectResponse
    {
        try {
            $queued = $this->cashier->submit(
                CashierRequestType::WorkerAdvance,
                [
                    ...$request->validated(),
                    'worker_id' => $worker->id,
                    'description' => 'Advance to '.$worker->name
                        .' — Rs. '.number_format((float) $request->input('amount'), 2),
                ],
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        if ($queued->isPending()) {
            return $this->backToWeek($worker, $request->input('payment_date'))
                ->with('success', 'Sent to the cashier. Worker totals update when the cashier pays.');
        }

        return $this->backToWeek($worker, $request->input('payment_date'))
            ->with('success', $request->boolean('deduct_from_week')
                ? 'Advance paid and deducted from this week.'
                : 'Advance paid and added to worker debt.');
    }

    public function settle(SettleWorkerWeekRequest $request, Worker $worker, WorkerPayrollWeek $week): RedirectResponse
    {
        abort_unless($week->worker_id === $worker->id, 404);

        try {
            $queued = $this->cashier->submit(
                CashierRequestType::WorkerSettlement,
                [
                    ...$request->validated(),
                    'worker_id' => $worker->id,
                    'description' => 'Saturday wages for '.$worker->name.' ('.$week->label().')',
                ],
                $request->user(),
                $week,
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        if ($queued->isPending()) {
            return $this->backToWeek($worker, $week->week_start)
                ->with('success', 'Sent to the cashier. The week settles when the cashier pays.');
        }

        return $this->backToWeek($worker, $week->week_start)->with('success', 'Week settled.');
    }

    public function reopen(Request $request, Worker $worker, WorkerPayrollWeek $week): RedirectResponse
    {
        $this->authorize('managePayroll', $worker);
        abort_unless($week->worker_id === $worker->id, 404);

        try {
            $this->payroll->reopenWeek($week, $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $this->backToWeek($worker, $week->week_start)
            ->with('success', 'Week reopened. Correct it, then settle again.');
    }

    public function storeWorkDay(WorkerWorkDayRequest $request, Worker $worker): RedirectResponse
    {
        try {
            $day = $this->payroll->recordWorkDay($worker, $request->validated(), $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return $this->backToWeek($worker, $day->work_date)->with('success', 'Work day added.');
    }

    public function updateWorkDay(WorkerWorkDayRequest $request, Worker $worker, WorkerWorkDay $workDay): RedirectResponse
    {
        abort_unless($workDay->worker_id === $worker->id, 404);

        try {
            $this->payroll->updateWorkDay($workDay, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return $this->backToWeek($worker, $workDay->work_date)->with('success', 'Work day updated.');
    }

    public function destroyWorkDay(Request $request, Worker $worker, WorkerWorkDay $workDay): RedirectResponse
    {
        $this->authorize('recordWork', $worker);
        abort_unless($workDay->worker_id === $worker->id, 404);

        $date = $workDay->work_date;

        try {
            $this->payroll->removeWorkDay($workDay);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $this->backToWeek($worker, $date)->with('success', 'Work day removed.');
    }

    private function backToWeek(Worker $worker, string|Carbon $date): RedirectResponse
    {
        return redirect()->route('construction.workers.payroll', [
            'worker' => $worker,
            'week' => $this->payroll->weekStartFor($date)->toDateString(),
        ]);
    }
}
