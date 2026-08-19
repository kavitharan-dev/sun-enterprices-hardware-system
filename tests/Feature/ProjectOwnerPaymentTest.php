<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectOwnerPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Project Budget − Total Site Owner Payments = Amount Still to Receive.
     *
     * Budget 4,500,000. Owner pays 500,000 then 750,000.
     * Still to receive = 3,250,000.
     */
    public function test_owner_payments_reduce_the_amount_still_to_receive_from_the_budget(): void
    {
        [$admin, $site] = $this->seedRoles();
        $project = $this->project($site, 4500000);

        $this->actingAs($admin)
            ->post(route('construction.projects.owner-payments.store', $project), [
                'amount' => 500000,
                'payment_date' => '2026-08-01',
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->confirmPendingCashierRequests($admin);

        $this->actingAs($admin)
            ->post(route('construction.projects.owner-payments.store', $project), [
                'amount' => 750000,
                'payment_date' => '2026-08-10',
                'method' => PaymentMethod::BankTransfer->value,
                'reference' => 'SLIP-102',
            ])
            ->assertRedirect();

        $this->confirmPendingCashierRequests($admin);

        $project = $project->fresh();

        $this->assertSame(1250000.0, $project->totalReceived());
        $this->assertSame(3250000.0, $project->remainingToReceive());
        $this->assertSame(1250000.0, $project->cashBalance());
    }

    /**
     * Owner payments and expenses are independent:
     * Budget remaining to receive stays 3,250,000 after 1,000,000 of expenses,
     * while cash balance becomes 250,000.
     */
    public function test_expenses_change_cash_balance_without_changing_amount_still_to_receive(): void
    {
        [, $site] = $this->seedRoles();
        $project = $this->project($site, 4500000);

        $this->actingAs($site)
            ->post(route('construction.projects.owner-payments.store', $project), [
                'amount' => 500000,
                'payment_date' => '2026-08-01',
                'method' => PaymentMethod::Cash->value,
            ]);

        $this->actingAs($site)
            ->post(route('construction.projects.owner-payments.store', $project), [
                'amount' => 750000,
                'payment_date' => '2026-08-10',
                'method' => PaymentMethod::Cash->value,
            ]);

        $this->confirmPendingCashierRequests();

        ProjectExpense::query()->create([
            'project_id' => $project->id,
            'category' => ExpenseCategory::Labour,
            'amount' => 1000000,
            'expense_date' => '2026-08-12',
            'description' => 'Development costs',
            'created_by' => $site->id,
        ]);

        $project = $project->fresh();

        $this->assertSame(4500000.0, (float) $project->budget);
        $this->assertSame(1250000.0, $project->totalReceived());
        $this->assertSame(3250000.0, $project->remainingToReceive());
        $this->assertSame(1000000.0, $project->totalSpent());
        $this->assertSame(250000.0, $project->cashBalance());
    }

    public function test_another_owner_payment_updates_both_totals(): void
    {
        [, $site] = $this->seedRoles();
        $project = $this->project($site, 4500000);

        $this->actingAs($site)->post(route('construction.projects.owner-payments.store', $project), [
            'amount' => 1250000,
            'payment_date' => '2026-08-01',
            'method' => PaymentMethod::Cash->value,
        ]);

        ProjectExpense::query()->create([
            'project_id' => $project->id,
            'category' => ExpenseCategory::Transport,
            'amount' => 1000000,
            'expense_date' => '2026-08-12',
            'description' => 'Development costs',
            'created_by' => $site->id,
        ]);

        $this->actingAs($site)->post(route('construction.projects.owner-payments.store', $project), [
            'amount' => 500000,
            'payment_date' => '2026-08-20',
            'method' => PaymentMethod::Cash->value,
        ]);

        $this->confirmPendingCashierRequests();

        $project = $project->fresh();

        $this->assertSame(1750000.0, $project->totalReceived());
        $this->assertSame(2750000.0, $project->remainingToReceive());
        $this->assertSame(750000.0, $project->cashBalance());
    }

    public function test_project_page_shows_both_independent_totals(): void
    {
        [$admin, $site] = $this->seedRoles();
        $project = $this->project($site, 4500000);

        $this->actingAs($admin)->post(route('construction.projects.owner-payments.store', $project), [
            'amount' => 1250000,
            'payment_date' => now()->toDateString(),
            'method' => PaymentMethod::Cash->value,
        ]);

        $this->confirmPendingCashierRequests($admin);

        ProjectExpense::query()->create([
            'project_id' => $project->id,
            'category' => ExpenseCategory::Labour,
            'amount' => 1000000,
            'expense_date' => now()->toDateString(),
            'description' => 'Labour',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('construction.projects.show', $project))
            ->assertOk()
            ->assertSee('Still to receive')
            ->assertSee('Rs. 3,250,000.00')
            ->assertSee('Cash balance after expenses')
            ->assertSee('Rs. 250,000.00')
            ->assertSee('Site owner payments');
    }

    public function test_unassigned_site_manager_cannot_record_owner_payments(): void
    {
        [, $site, $other] = $this->seedRoles();
        $project = $this->project($other, 4500000);

        $this->actingAs($site)
            ->post(route('construction.projects.owner-payments.store', $project), [
                'amount' => 500000,
                'payment_date' => now()->toDateString(),
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function seedRoles(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('site_manager');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $site = User::factory()->create();
        $site->assignRole('site_manager');

        $other = User::factory()->create();
        $other->assignRole('site_manager');

        return [$admin, $site, $other];
    }

    private function project(User $site, float $budget): Project
    {
        $admin = User::query()->role('admin')->first() ?? User::factory()->create();

        return Project::query()->create([
            'project_code' => 'PRJ-2026-0900',
            'name' => 'Nilaveli Villa',
            'customer_id' => Customer::query()->create(['name' => 'Site Owner'])->id,
            'location' => 'Nilaveli',
            'budget' => $budget,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'progress_percentage' => 0,
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);
    }
}
