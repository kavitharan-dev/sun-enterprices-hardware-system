<?php

namespace Tests\Feature;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Enums\WorkerStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyAccountEntry;
use App\Models\Product;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Services\DailyAccountService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\WorkerPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_cash_sale_posts_income_to_daily_accounts(): void
    {
        [$user, $product] = $this->shopUser();

        $sale = app(SaleService::class)->create([
            'customer_id' => null,
            'walk_in_name' => 'Nimal',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2350],
        ], $user->id);

        app(SaleService::class)->complete($sale, [
            'amount' => 2350,
            'method' => 'cash',
        ], $user->id);

        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::Sale->value,
            'category' => DailyAccountCategory::HardwareSale->value,
            'income' => 2350,
            'expense' => 0,
        ]);
    }

    public function test_credit_sale_does_not_post_to_daily_accounts(): void
    {
        [$user, $product] = $this->shopUser();

        $sale = app(SaleService::class)->create([
            'customer_id' => Customer::query()->create(['name' => 'Credit customer'])->id,
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ], $user->id);

        app(SaleService::class)->complete($sale, [
            'amount' => 0,
            'method' => 'credit',
        ], $user->id);

        $this->assertSame(0, DailyAccountEntry::query()->count());
    }

    public function test_completing_a_purchase_posts_expense_and_increases_stock(): void
    {
        [$user, $product] = $this->shopUser();
        $supplier = Supplier::query()->create(['name' => 'Ceylon Cement']);

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 2000],
        ], $user->id);

        app(PurchaseService::class)->complete($purchase, $user->id);

        $this->assertSame(110.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::Purchase->value,
            'expense' => 20000,
            'income' => 0,
        ]);
    }

    public function test_worker_advance_and_owner_payment_post_to_the_same_ledger(): void
    {
        [$admin, $site] = $this->constructionUsers();
        $worker = $this->worker();
        $project = $this->project($site, 4500000);

        app(WorkerPayrollService::class)->recordAdvance($worker, [
            'amount' => 3000,
            'payment_date' => '2026-08-19',
            'deduct_from_week' => true,
            'project_id' => $project->id,
        ], $admin->id);

        $this->actingAs($admin)->post(route('construction.projects.owner-payments.store', $project), [
            'amount' => 500000,
            'payment_date' => '2026-08-19',
            'method' => PaymentMethod::Cash->value,
        ])->assertRedirect();

        $this->confirmPendingCashierRequests($admin);

        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::WorkerAdvance->value,
            'worker_id' => $worker->id,
            'expense' => 3000,
        ]);

        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::OwnerPayment->value,
            'project_id' => $project->id,
            'income' => 500000,
        ]);
    }

    public function test_manual_site_expense_posts_but_automatic_labour_expense_does_not_double_count(): void
    {
        [$admin, $site] = $this->constructionUsers();
        $worker = $this->worker();
        $project = $this->project($site, 100000);

        $this->actingAs($site)->post(route('construction.projects.expenses.store', $project), [
            'category' => ExpenseCategory::Transport->value,
            'amount' => 4000,
            'expense_date' => '2026-08-19',
            'description' => 'Lorry hire',
        ])->assertRedirect();

        $this->confirmPendingCashierRequests($admin);

        $this->assertSame(1, DailyAccountEntry::query()->where('type', DailyAccountType::ProjectExpense)->count());

        $payroll = app(WorkerPayrollService::class);
        $payroll->recordWorkDay($worker, [
            'work_date' => '2026-08-19',
            'project_id' => $project->id,
            'daily_amount' => 10000,
        ], $site->id);

        $week = $payroll->weekFor($worker, '2026-08-19');
        $payroll->settleWeek($week, ['amount' => 10000, 'debt_deducted' => 0], $admin->id);

        $this->assertSame(1, DailyAccountEntry::query()->where('type', DailyAccountType::WorkerSettlement)->count());
        $this->assertSame(1, DailyAccountEntry::query()->where('type', DailyAccountType::ProjectExpense)->count());
    }

    public function test_opening_plus_income_minus_expenses_is_the_closing_balance(): void
    {
        $admin = $this->admin();
        $accounts = app(DailyAccountService::class);
        $date = now()->toDateString();

        $accounts->setOpening($date, 10000, $admin->id);

        $accounts->post([
            'occurred_on' => $date,
            'type' => DailyAccountType::OtherIncome,
            'category' => DailyAccountCategory::OtherIncome,
            'description' => 'Scrap sale',
            'income' => 2500,
            'is_manual' => true,
            'recorded_by' => $admin->id,
        ]);

        $accounts->post([
            'occurred_on' => $date,
            'type' => DailyAccountType::OtherExpense,
            'category' => DailyAccountCategory::Other,
            'description' => 'Tea',
            'expense' => 500,
            'is_manual' => true,
            'recorded_by' => $admin->id,
        ]);

        $totals = $accounts->totalsFor($date);

        $this->assertSame(10000.0, $totals['opening']);
        $this->assertSame(2500.0, $totals['income']);
        $this->assertSame(500.0, $totals['expense']);
        $this->assertSame(12000.0, $totals['closing']);
    }

    public function test_cashier_can_open_daily_accounts_and_site_manager_cannot(): void
    {
        Role::findOrCreate('cashier');
        Role::findOrCreate('site_manager');

        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $site = User::factory()->create();
        $site->assignRole('site_manager');

        $this->actingAs($cashier)
            ->get(route('cashier.daily-accounts.index'))
            ->assertOk()
            ->assertSee('Daily accounts')
            ->assertSee('Closing balance');

        $this->actingAs($site)
            ->get(route('cashier.daily-accounts.index'))
            ->assertForbidden();
    }

    public function test_cashier_can_record_other_income_without_retyping_a_sale(): void
    {
        Role::findOrCreate('cashier');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->post(route('cashier.daily-accounts.store'), [
                'occurred_on' => now()->toDateString(),
                'type' => DailyAccountType::OtherIncome->value,
                'category' => DailyAccountCategory::OtherIncome->value,
                'description' => 'Bank interest',
                'amount' => 1500,
                'method' => PaymentMethod::BankTransfer->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('daily_account_entries', [
            'description' => 'Bank interest',
            'income' => 1500,
            'is_manual' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function shopUser(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('store_manager');
        Role::findOrCreate('cashier');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $category = Category::query()->create(['name' => 'Cement']);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag']);
        $product = Product::query()->create([
            'sku' => 'CEM-DA-1',
            'name' => 'Cement 50kg',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 2000,
            'selling_price' => 2350,
            'min_stock_level' => 10,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        return [$user, $product];
    }

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function constructionUsers(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('site_manager');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $site = User::factory()->create();
        $site->assignRole('site_manager');

        return [$admin, $site];
    }

    private function worker(): Worker
    {
        return Worker::query()->create([
            'worker_code' => 'WRK-DA-1',
            'name' => 'Sunil Perera',
            'job_role' => 'Mason',
            'daily_rate' => 0,
            'weekly_salary' => 10000,
            'status' => WorkerStatus::Active,
        ]);
    }

    private function project(User $site, float $budget): Project
    {
        return Project::query()->create([
            'project_code' => 'PRJ-DA-1',
            'name' => 'Nilaveli Villa',
            'customer_id' => Customer::query()->create(['name' => 'Owner'])->id,
            'location' => 'Nilaveli',
            'budget' => $budget,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'progress_percentage' => 0,
            'site_manager_id' => $site->id,
            'created_by' => $site->id,
        ]);
    }
}
