<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\ProjectStatus;
use App\Enums\WorkerPaymentType;
use App\Enums\WorkerStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPayrollWeek;
use App\Services\WorkerPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkerPayrollTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scenario 1 from the shop's rules.
     *
     * Weekly salary 10,000. Rs. 3,000 taken before Saturday WITH
     * "deduct from current week" = yes. Saturday payout 5,000.
     * Total paid 8,000, remaining 2,000, no debt.
     */
    public function test_advance_deducted_from_the_week_reduces_the_saturday_payout(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $payroll->recordAdvance($worker, [
            'amount' => 3000,
            'payment_date' => $this->wednesday(),
            'deduct_from_week' => true,
        ], $admin->id);

        $week = $payroll->weekFor($worker, $this->wednesday())->load('payments');

        $this->assertSame(10000.0, (float) $week->weekly_salary);
        $this->assertSame(3000.0, $week->advancesDeducted());
        $this->assertSame(7000.0, $week->netPayable());

        $payroll->settleWeek($week, ['amount' => 5000, 'debt_deducted' => 0], $admin->id);

        $week = $week->fresh()->load('payments');

        $this->assertSame(8000.0, $week->totalPaid());
        $this->assertSame(2000.0, $week->remainingSalary());
        $this->assertSame(0.0, $worker->fresh()->debtBalance());
        $this->assertTrue($week->isSettled());
    }

    /**
     * Scenario 2 from the shop's rules.
     *
     * Same 3,000 before Saturday but "deduct from current week" = no.
     * Worker still receives the full 10,000 on Saturday, and the 3,000
     * becomes debt.
     */
    public function test_advance_not_deducted_becomes_worker_debt(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $payroll->recordAdvance($worker, [
            'amount' => 3000,
            'payment_date' => $this->wednesday(),
            'deduct_from_week' => false,
        ], $admin->id);

        $week = $payroll->weekFor($worker, $this->wednesday())->load('payments');

        $this->assertSame(0.0, $week->advancesDeducted());
        $this->assertSame(3000.0, $week->advancesToDebt());
        $this->assertSame(10000.0, $week->netPayable());

        $payroll->settleWeek($week, ['amount' => 10000, 'debt_deducted' => 0], $admin->id);

        $week = $week->fresh()->load('payments');

        $this->assertSame(0.0, $week->remainingSalary());
        $this->assertSame(13000.0, $week->totalPaid());
        $this->assertSame(3000.0, $worker->fresh()->debtBalance());
    }

    /**
     * Next week: debt of 3,000 recovered out of a 10,000 salary leaves 7,000
     * for the worker and clears the debt.
     */
    public function test_debt_is_recovered_from_a_later_week_when_deducted(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $this->giveUndeductedAdvance($worker, $admin, 3000);
        $this->assertSame(3000.0, $worker->fresh()->debtBalance());

        $nextWeek = $payroll->weekFor($worker->fresh(), $this->wednesday()->addWeek(), $admin->id);
        $payroll->settleWeek($nextWeek, ['amount' => 7000, 'debt_deducted' => 3000], $admin->id);

        $nextWeek = $nextWeek->fresh()->load('payments');

        $this->assertSame(7000.0, $nextWeek->netPayable());
        $this->assertSame(7000.0, $nextWeek->settlementsPaid());
        $this->assertSame(0.0, $nextWeek->remainingSalary());
        $this->assertSame(0.0, $worker->fresh()->debtBalance());
    }

    /**
     * The same next week, but the shop chooses not to recover the debt: the
     * worker gets the full salary and still owes 3,000.
     */
    public function test_debt_carries_forward_when_not_deducted(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $this->giveUndeductedAdvance($worker, $admin, 3000);

        $nextWeek = $payroll->weekFor($worker->fresh(), $this->wednesday()->addWeek(), $admin->id);
        $payroll->settleWeek($nextWeek, ['amount' => 10000, 'debt_deducted' => 0], $admin->id);

        $this->assertSame(10000.0, $nextWeek->fresh()->load('payments')->settlementsPaid());
        $this->assertSame(3000.0, $worker->fresh()->debtBalance());
    }

    public function test_deductible_advance_cannot_exceed_the_remaining_salary(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $this->expectException(RuntimeException::class);

        $payroll->recordAdvance($worker, [
            'amount' => 12000,
            'payment_date' => $this->wednesday(),
            'deduct_from_week' => true,
        ], $admin->id);
    }

    public function test_debt_recovery_cannot_exceed_what_the_worker_owes(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $this->giveUndeductedAdvance($worker, $admin, 1000);

        $nextWeek = $payroll->weekFor($worker->fresh(), $this->wednesday()->addWeek(), $admin->id);

        $this->expectException(RuntimeException::class);

        $payroll->settleWeek($nextWeek, ['amount' => 0, 'debt_deducted' => 5000], $admin->id);
    }

    public function test_a_settled_week_cannot_be_settled_twice(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $week = $payroll->weekFor($worker, $this->wednesday(), $admin->id);
        $payroll->settleWeek($week, ['amount' => 10000, 'debt_deducted' => 0], $admin->id);

        $this->expectException(RuntimeException::class);

        $payroll->settleWeek($week->fresh(), ['amount' => 100, 'debt_deducted' => 0], $admin->id);
    }

    public function test_work_sheet_records_the_day_and_site_and_splits_wages_between_projects(): void
    {
        [$admin, $site] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $projectA = $this->project($site, 'PRJ-A', 'Site A');
        $projectB = $this->project($site, 'PRJ-B', 'Site B');
        $payroll = app(WorkerPayrollService::class);

        $monday = $this->wednesday()->startOfWeek(Carbon::SUNDAY)->addDay();

        foreach ([0, 1, 2] as $offset) {
            $payroll->recordWorkDay($worker, [
                'work_date' => $monday->copy()->addDays($offset)->toDateString(),
                'project_id' => $offset === 2 ? $projectB->id : $projectA->id,
            ], $site->id);
        }

        $week = $payroll->weekFor($worker, $this->wednesday())->load('workDays');

        $this->assertCount(3, $week->workDays);
        $this->assertSame('Monday', $week->workDays->first()->dayName());
        $this->assertSame('Site A', $week->workDays->first()->siteName());

        $payroll->settleWeek($week, ['amount' => 10000, 'debt_deducted' => 0], $admin->id);

        // Two days on Site A, one on Site B, out of Rs. 10,000 paid.
        $this->assertSame(
            6666.67,
            (float) ProjectExpense::query()
                ->where('project_id', $projectA->id)
                ->where('category', ExpenseCategory::Labour)
                ->value('amount'),
        );

        $this->assertSame(
            3333.33,
            (float) ProjectExpense::query()
                ->where('project_id', $projectB->id)
                ->where('category', ExpenseCategory::Labour)
                ->value('amount'),
        );
    }

    public function test_the_same_day_cannot_be_recorded_twice(): void
    {
        [, $site] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $payroll->recordWorkDay($worker, ['work_date' => $this->wednesday()->toDateString()], $site->id);

        $this->expectException(RuntimeException::class);

        $payroll->recordWorkDay($worker, ['work_date' => $this->wednesday()->toDateString()], $site->id);
    }

    public function test_admin_can_record_an_advance_through_the_form(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);

        $this->actingAs($admin)
            ->post(route('construction.workers.payroll.advances.store', $worker), [
                'amount' => 3000,
                'payment_date' => $this->wednesday()->toDateString(),
                'deduct_from_week' => '0',
                'notes' => 'Family need',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('worker_payments', [
            'worker_id' => $worker->id,
            'type' => WorkerPaymentType::Advance->value,
            'amount' => 3000,
            'deduct_from_week' => false,
        ]);

        $this->assertSame(3000.0, $worker->fresh()->debtBalance());
    }

    public function test_site_manager_can_fill_the_work_sheet_but_cannot_pay_wages(): void
    {
        [, $site] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);

        $this->actingAs($site)
            ->post(route('construction.workers.payroll.work-days.store', $worker), [
                'work_date' => $this->wednesday()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('worker_work_days', ['worker_id' => $worker->id]);

        $this->actingAs($site)
            ->post(route('construction.workers.payroll.advances.store', $worker), [
                'amount' => 1000,
                'payment_date' => $this->wednesday()->toDateString(),
                'deduct_from_week' => '1',
            ])
            ->assertForbidden();
    }

    public function test_payroll_page_shows_an_open_week_with_the_advance_choice(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);

        // Left open on purpose: the advance and settlement forms only show
        // while the week is still open.
        app(WorkerPayrollService::class)->recordAdvance($worker, [
            'amount' => 3000,
            'payment_date' => $this->wednesday(),
            'deduct_from_week' => false,
        ], $admin->id);

        $this->actingAs($admin)
            ->get(route('construction.workers.payroll', ['worker' => $worker, 'week' => $this->wednesday()->toDateString()]))
            ->assertOk()
            ->assertSee('Weekly salary')
            ->assertSee('Worker debt')
            ->assertSee('Work sheet')
            ->assertSee('Deduct from current week salary?')
            ->assertSee('Added to worker debt')
            ->assertSee('Recover debt now (Rs.)');
    }

    public function test_payroll_page_locks_a_settled_week(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);

        $this->giveUndeductedAdvance($worker, $admin, 3000);

        $this->actingAs($admin)
            ->get(route('construction.workers.payroll', ['worker' => $worker, 'week' => $this->wednesday()->toDateString()]))
            ->assertOk()
            ->assertSee('Week settled on')
            ->assertDontSee('Deduct from current week salary?');
    }

    public function test_a_settled_week_can_be_reopened_and_settled_again(): void
    {
        [$admin, $site] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $project = $this->project($site, 'PRJ-C', 'Site C');
        $payroll = app(WorkerPayrollService::class);

        $payroll->recordWorkDay($worker, [
            'work_date' => $this->wednesday()->toDateString(),
            'project_id' => $project->id,
        ], $site->id);

        $week = $payroll->weekFor($worker, $this->wednesday());
        // Paid too little by mistake.
        $payroll->settleWeek($week, ['amount' => 4000, 'debt_deducted' => 0], $admin->id);

        $this->assertSame(1, ProjectExpense::query()->where('project_id', $project->id)->count());

        $payroll->reopenWeek($week->fresh(), $admin->id);
        $week = $week->fresh()->load('payments');

        $this->assertFalse($week->isSettled());
        $this->assertSame(0, ProjectExpense::query()->where('project_id', $project->id)->count());
        $this->assertSame(4000.0, $week->settlementsPaid());
        $this->assertSame(6000.0, $week->remainingSalary());

        $payroll->settleWeek($week, ['amount' => 6000, 'debt_deducted' => 0], $admin->id);
        $week = $week->fresh()->load('payments');

        $this->assertSame(10000.0, $week->settlementsPaid());
        $this->assertSame(0.0, $week->remainingSalary());
        $this->assertSame(
            10000.0,
            (float) ProjectExpense::query()->where('project_id', $project->id)->value('amount'),
        );
    }

    /**
     * Reopening keeps the debt recovery already on record. Settling again
     * replaces that figure, so the same amount can be re-entered, and dropping
     * it back to zero puts the debt back on the worker.
     */
    public function test_debt_recovery_can_be_re_entered_or_undone_after_reopening(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);
        $payroll = app(WorkerPayrollService::class);

        $this->giveUndeductedAdvance($worker, $admin, 3000);

        $nextWeek = $payroll->weekFor($worker->fresh(), $this->wednesday()->addWeek(), $admin->id);
        $payroll->settleWeek($nextWeek, ['amount' => 7000, 'debt_deducted' => 3000], $admin->id);
        $this->assertSame(0.0, $worker->fresh()->debtBalance());

        $payroll->reopenWeek($nextWeek->fresh(), $admin->id);
        $this->assertSame(0.0, $worker->fresh()->debtBalance());

        // Re-entering the same recovery must not be rejected as "more than owed".
        $payroll->settleWeek($nextWeek->fresh(), ['amount' => 0, 'debt_deducted' => 3000], $admin->id);
        $this->assertSame(0.0, $worker->fresh()->debtBalance());

        $payroll->reopenWeek($nextWeek->fresh(), $admin->id);
        $payroll->settleWeek($nextWeek->fresh(), ['amount' => 0, 'debt_deducted' => 0], $admin->id);

        $this->assertSame(3000.0, $worker->fresh()->debtBalance());
    }

    public function test_payday_overview_lists_active_workers_with_what_is_still_owed(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);

        app(WorkerPayrollService::class)->recordAdvance($worker, [
            'amount' => 4000,
            'payment_date' => $this->wednesday(),
            'deduct_from_week' => true,
        ], $admin->id);

        $this->actingAs($admin)
            ->get(route('construction.payroll.index', ['week' => $this->wednesday()->toDateString()]))
            ->assertOk()
            ->assertSee('Weekly wages')
            ->assertSee('Sunil Perera')
            // 10,000 salary less the 4,000 advance already taken.
            ->assertSee('Rs. 6,000.00')
            ->assertSee('Open');
    }

    public function test_week_runs_sunday_to_saturday(): void
    {
        [$admin] = $this->seedRoles();
        $worker = $this->worker(weeklySalary: 10000);

        $week = app(WorkerPayrollService::class)->weekFor($worker, '2026-08-19', $admin->id);

        $this->assertSame('Sunday', $week->week_start->format('l'));
        $this->assertSame('Saturday', $week->week_end->format('l'));
        $this->assertSame('2026-08-16', $week->week_start->toDateString());
        $this->assertSame('2026-08-22', $week->week_end->toDateString());
    }

    private function giveUndeductedAdvance(Worker $worker, User $admin, float $amount): WorkerPayrollWeek
    {
        $payroll = app(WorkerPayrollService::class);

        $payroll->recordAdvance($worker, [
            'amount' => $amount,
            'payment_date' => $this->wednesday(),
            'deduct_from_week' => false,
        ], $admin->id);

        $week = $payroll->weekFor($worker, $this->wednesday());
        $payroll->settleWeek($week, ['amount' => 10000, 'debt_deducted' => 0], $admin->id);

        return $week->fresh();
    }

    private function wednesday(): Carbon
    {
        return Carbon::parse('2026-08-19');
    }

    private function worker(float $weeklySalary): Worker
    {
        return Worker::query()->create([
            'worker_code' => 'WRK-TEST-0001',
            'name' => 'Sunil Perera',
            'job_role' => 'Mason',
            'daily_rate' => 0,
            'weekly_salary' => $weeklySalary,
            'join_date' => now()->subYear()->toDateString(),
            'status' => WorkerStatus::Active,
        ]);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function seedRoles(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('site_manager');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $site = User::factory()->create();
        $site->assignRole('site_manager');

        return [$admin, $site];
    }

    private function project(User $site, string $code, string $name): Project
    {
        return Project::query()->create([
            'project_code' => $code,
            'name' => $name,
            'customer_id' => Customer::query()->create(['name' => 'Kumar '.$code])->id,
            'location' => 'Trincomalee',
            'budget' => 500000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'progress_percentage' => 0,
            'site_manager_id' => $site->id,
            'created_by' => $site->id,
        ]);
    }
}
