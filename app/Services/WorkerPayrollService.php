<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\WorkerPaymentType;
use App\Models\ProjectExpense;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\Models\WorkerPayrollWeek;
use App\Models\WorkerWorkDay;
use App\Traits\LogsActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkerPayrollService
{
    use LogsActivity;

    /**
     * Payday. The week runs Sunday to Saturday so that Saturday closes it.
     */
    public const PAYDAY = CarbonInterface::SATURDAY;

    public const WEEK_START = CarbonInterface::SUNDAY;

    public function __construct(private readonly DailyAccountService $dailyAccounts) {}

    public function weekStartFor(string|Carbon $date): Carbon
    {
        return Carbon::parse($date)->startOfWeek(self::WEEK_START);
    }

    /**
     * Find or open the pay week that contains the given date. The weekly salary
     * is copied onto the week so later salary changes cannot rewrite history.
     */
    public function weekFor(Worker $worker, string|Carbon $date, ?int $userId = null): WorkerPayrollWeek
    {
        $start = $this->weekStartFor($date);

        $week = WorkerPayrollWeek::query()
            ->where('worker_id', $worker->id)
            ->whereDate('week_start', $start)
            ->first();

        if ($week) {
            return $week;
        }

        return WorkerPayrollWeek::query()->create([
            'worker_id' => $worker->id,
            'week_start' => $start->toDateString(),
            'week_end' => $start->copy()->endOfWeek(self::PAYDAY)->toDateString(),
            'weekly_salary' => round((float) $worker->weekly_salary, 2),
            'debt_deducted' => 0,
            'created_by' => $userId,
        ]);
    }

    /**
     * Money handed over before Saturday.
     *
     * When $deductFromWeek is true it comes off this week's wage. When false the
     * worker still receives the full wage on Saturday and the advance becomes
     * debt carried into later weeks.
     */
    public function recordAdvance(Worker $worker, array $data, ?int $userId = null): WorkerPayment
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new RuntimeException('Enter the amount given to the worker.');
        }

        return DB::transaction(function () use ($worker, $data, $amount, $userId) {
            $date = Carbon::parse($data['payment_date'] ?? now()->toDateString());
            $week = $this->weekFor($worker, $date, $userId);

            if ($week->isSettled()) {
                throw new RuntimeException('This week is already settled. Record the advance against the current week instead.');
            }

            $deduct = (bool) ($data['deduct_from_week'] ?? true);

            if ($deduct) {
                $week->load('payments');
                $available = $week->remainingSalary();

                if ($amount > $available) {
                    throw new RuntimeException(
                        'Only Rs. '.number_format($available, 2).' of this week\'s salary is left. '
                        .'Reduce the amount, or choose not to deduct it so the balance becomes worker debt.',
                    );
                }
            }

            $payment = WorkerPayment::query()->create([
                'worker_id' => $worker->id,
                'worker_payroll_week_id' => $week->id,
                'project_id' => $data['project_id'] ?? null,
                'type' => WorkerPaymentType::Advance,
                'payment_date' => $date->toDateString(),
                'amount' => $amount,
                'deduct_from_week' => $deduct,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $userId,
            ]);

            $this->dailyAccounts->postWorkerPayment($payment->load(['worker', 'week']));

            $this->logActivity(
                'advance',
                'WorkerPayment',
                'Advance Rs. '.number_format($amount, 2)." to {$worker->name} — "
                    .($deduct ? 'deducted from this week' : 'added to worker debt'),
                $payment,
            );

            return $payment;
        });
    }

    /**
     * Close the week on Saturday: pay what is left, optionally recovering old
     * debt out of this week's wage.
     */
    public function settleWeek(WorkerPayrollWeek $week, array $data, ?int $userId = null): WorkerPayrollWeek
    {
        return DB::transaction(function () use ($week, $data, $userId) {
            $week->load(['payments', 'worker']);

            if ($week->isSettled()) {
                throw new RuntimeException('This week has already been settled.');
            }

            $worker = $week->worker;
            $debtToRecover = round((float) ($data['debt_deducted'] ?? 0), 2);

            if ($debtToRecover < 0) {
                throw new RuntimeException('Debt deduction cannot be negative.');
            }

            // Add back what this week already recovered: settling again replaces
            // that figure rather than recovering on top of it.
            $outstandingDebt = round($worker->debtBalance() + (float) $week->debt_deducted, 2);

            if ($debtToRecover > $outstandingDebt) {
                throw new RuntimeException(
                    'This worker owes Rs. '.number_format($outstandingDebt, 2).'. You cannot recover more than that.',
                );
            }

            $week->update(['debt_deducted' => $debtToRecover]);
            $week->refresh()->load('payments');

            $payout = round((float) ($data['amount'] ?? $week->remainingSalary()), 2);
            $available = $week->remainingSalary();

            if ($payout < 0) {
                throw new RuntimeException('Payout cannot be negative.');
            }

            if ($payout > $available) {
                throw new RuntimeException(
                    'Only Rs. '.number_format($available, 2).' is left for this week after advances and debt recovery.',
                );
            }

            if ($payout > 0) {
                $settlement = WorkerPayment::query()->create([
                    'worker_id' => $worker->id,
                    'worker_payroll_week_id' => $week->id,
                    'project_id' => $data['project_id'] ?? null,
                    'type' => WorkerPaymentType::Settlement,
                    'payment_date' => $data['payment_date'] ?? $week->week_end->toDateString(),
                    'amount' => $payout,
                    'deduct_from_week' => true,
                    'notes' => $data['notes'] ?? null,
                    'recorded_by' => $userId,
                ]);

                $this->dailyAccounts->postWorkerPayment($settlement->load(['worker', 'week']));
            }

            $week->update([
                'settled_at' => now(),
                'settled_by' => $userId,
                'notes' => $data['notes'] ?? $week->notes,
            ]);

            $week->refresh()->load('payments');
            $this->postLabourExpense($week, $userId);

            $this->logActivity(
                'settled',
                'WorkerPayrollWeek',
                "Settled {$worker->name} for {$week->label()} — paid Rs. ".number_format($week->totalPaid(), 2)
                    .($debtToRecover > 0 ? ', recovered debt Rs. '.number_format($debtToRecover, 2) : ''),
                $week,
            );

            return $week;
        });
    }

    /**
     * Unlock a settled week so a mistake can be corrected. Money already handed
     * over stays on the record; the labour expense is removed and posted again
     * when the week is settled afresh.
     */
    public function reopenWeek(WorkerPayrollWeek $week, ?int $userId = null): WorkerPayrollWeek
    {
        return DB::transaction(function () use ($week) {
            if (! $week->isSettled()) {
                throw new RuntimeException('This week is still open.');
            }

            $week->expenses()->delete();
            $week->update(['settled_at' => null, 'settled_by' => null]);

            $this->logActivity(
                'reopened',
                'WorkerPayrollWeek',
                "Reopened {$week->worker->name} pay week {$week->label()} for correction",
                $week,
            );

            return $week->fresh();
        });
    }

    /**
     * Record a day the worker was on site. The week is opened automatically so
     * the sheet can be filled in as the week goes along.
     */
    public function recordWorkDay(Worker $worker, array $data, ?int $userId = null): WorkerWorkDay
    {
        return DB::transaction(function () use ($worker, $data, $userId) {
            $date = Carbon::parse($data['work_date']);
            $week = $this->weekFor($worker, $date, $userId);

            $this->guardOpenSheet($week);

            $exists = WorkerWorkDay::query()
                ->where('worker_id', $worker->id)
                ->whereDate('work_date', $date)
                ->exists();

            if ($exists) {
                throw new RuntimeException('This day is already on the work sheet.');
            }

            $day = WorkerWorkDay::query()->create([
                'worker_id' => $worker->id,
                'worker_payroll_week_id' => $week->id,
                'project_id' => $data['project_id'] ?? null,
                'work_date' => $date->toDateString(),
                'daily_amount' => round((float) ($data['daily_amount'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $userId,
            ]);

            $this->syncWeeklySalary($week);

            return $day;
        });
    }

    public function updateWorkDay(WorkerWorkDay $day, array $data): WorkerWorkDay
    {
        return DB::transaction(function () use ($day, $data) {
            $week = $day->week;
            $this->guardOpenSheet($week);

            $day->update([
                'project_id' => $data['project_id'] ?? null,
                'daily_amount' => round((float) ($data['daily_amount'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncWeeklySalary($week);

            return $day->fresh();
        });
    }

    public function removeWorkDay(WorkerWorkDay $day): void
    {
        DB::transaction(function () use ($day) {
            $week = $day->week;
            $this->guardOpenSheet($week);

            $day->delete();
            $this->syncWeeklySalary($week);
        });
    }

    /**
     * The work sheet decides the week's salary: the day salaries added up. With
     * no day salaries entered it falls back to the worker's agreed weekly
     * figure, so a week can still be paid without filling the sheet in.
     */
    private function syncWeeklySalary(WorkerPayrollWeek $week): void
    {
        $week->load(['workDays', 'payments', 'worker']);

        $salary = $week->salaryFromSheet()
            ? $week->sheetTotal()
            : round((float) $week->worker->weekly_salary, 2);

        $committed = $week->committedSalary();

        if ($salary < $committed) {
            throw new RuntimeException(
                'Rs. '.number_format($committed, 2).' of this week has already been paid or set against debt, '
                .'so the work sheet cannot total less than that. Reverse the payment first.',
            );
        }

        $week->update(['weekly_salary' => $salary]);
    }

    private function guardOpenSheet(?WorkerPayrollWeek $week): void
    {
        if ($week?->isSettled()) {
            throw new RuntimeException('This week is already settled, so its work sheet cannot change.');
        }
    }

    /**
     * Wages are a project cost. Split what the worker was paid across the sites
     * they actually worked on that week, so each project carries its share.
     */
    private function postLabourExpense(WorkerPayrollWeek $week, ?int $userId): void
    {
        $paid = $week->totalPaid();

        if ($paid <= 0) {
            return;
        }

        $days = $week->workDays()->whereNotNull('project_id')->get();

        if ($days->isEmpty()) {
            return;
        }

        $perProject = $days->groupBy('project_id');
        $dayCount = $days->count();
        $allocated = 0.0;
        $index = 0;

        foreach ($perProject as $projectId => $projectDays) {
            $index++;

            // Give the last project the remainder so the split always adds up.
            $share = $index === $perProject->count()
                ? round($paid - $allocated, 2)
                : round($paid * ($projectDays->count() / $dayCount), 2);

            $allocated = round($allocated + $share, 2);

            if ($share <= 0) {
                continue;
            }

            ProjectExpense::query()->create([
                'project_id' => $projectId,
                'category' => ExpenseCategory::Labour,
                'amount' => $share,
                'expense_date' => $week->week_end->toDateString(),
                'description' => "{$week->worker->name} wages, {$week->label()} ({$projectDays->count()} of {$dayCount} days)",
                'reference_type' => $week::class,
                'reference_id' => $week->id,
                'created_by' => $userId,
            ]);
        }
    }
}
