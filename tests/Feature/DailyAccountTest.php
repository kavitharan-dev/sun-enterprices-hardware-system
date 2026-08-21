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
use App\Models\Payment;
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

        $payment = Payment::query()->first();
        $entry = DailyAccountEntry::query()->first();
        $this->assertSame($entry->id, $payment->daily_account_entry_id);
        $this->assertMatchesRegularExpression('/^TXN-\d{6}$/', $entry->transaction_no);

        app(DailyAccountService::class)->postSalePayment($payment);
        $this->assertSame(1, DailyAccountEntry::query()->count());
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
        $this->assertSame(
            DailyAccountEntry::query()->value('id'),
            $purchase->fresh()->daily_account_entry_id,
        );
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

        $this->cashierRecords([
            'occurred_on' => '2026-08-19',
            'type' => DailyAccountType::OwnerPayment->value,
            'amount' => 500000,
            'method' => PaymentMethod::Cash->value,
            'project_id' => $project->id,
        ], $admin);

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

        $this->cashierRecords([
            'occurred_on' => '2026-08-19',
            'type' => DailyAccountType::ProjectExpense->value,
            'amount' => 4000,
            'project_id' => $project->id,
            'expense_category' => ExpenseCategory::Transport->value,
            'description' => 'Lorry hire',
        ], $admin);

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

    public function test_cashier_can_close_the_day_and_print_the_till_report(): void
    {
        Role::findOrCreate('cashier');
        $cashier = User::factory()->create(['name' => 'Till Cashier']);
        $cashier->assignRole('cashier');
        $date = now()->toDateString();
        $accounts = app(DailyAccountService::class);

        $accounts->setOpening($date, 5000, $cashier->id);
        $accounts->post([
            'occurred_on' => $date,
            'type' => DailyAccountType::OtherIncome,
            'category' => DailyAccountCategory::OtherIncome,
            'description' => 'Counter float top-up',
            'income' => 1000,
            'is_manual' => true,
            'recorded_by' => $cashier->id,
        ]);

        $this->actingAs($cashier)
            ->post(route('cashier.daily-accounts.close'), [
                'business_date' => $date,
                'counted_cash' => 5950,
                'close_notes' => 'Short Rs. 50',
            ])
            ->assertRedirect(route('cashier.daily-accounts.print', ['date' => $date]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('daily_account_days', [
            'is_closed' => true,
            'closing_balance' => 6000,
            'counted_cash' => 5950,
            'closed_by' => $cashier->id,
        ]);
        $this->assertTrue(
            \App\Models\DailyAccountDay::query()->whereDate('business_date', $date)->where('is_closed', true)->exists()
        );

        $this->actingAs($cashier)
            ->get(route('cashier.daily-accounts.print', ['date' => $date]))
            ->assertOk()
            ->assertSee('DAILY TILL REPORT')
            ->assertSee('Counter float top-up')
            ->assertSee('Closed')
            ->assertSee('Variance');

        $this->actingAs($cashier)
            ->get(route('cashier.daily-accounts.pdf', ['date' => $date]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_closed_day_rejects_new_money_until_admin_reopens(): void
    {
        Role::findOrCreate('cashier');
        Role::findOrCreate('admin');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $date = now()->toDateString();

        app(DailyAccountService::class)->closeDay($date, $cashier, 1000);

        $this->actingAs($cashier)
            ->post(route('cashier.daily-accounts.store'), [
                'occurred_on' => $date,
                'type' => DailyAccountType::OtherIncome->value,
                'category' => DailyAccountCategory::OtherIncome->value,
                'description' => 'Too late',
                'amount' => 200,
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, DailyAccountEntry::query()->count());

        $this->actingAs($cashier)
            ->post(route('cashier.daily-accounts.reopen'), ['business_date' => $date])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('cashier.daily-accounts.reopen'), ['business_date' => $date])
            ->assertRedirect(route('cashier.daily-accounts.index', ['from' => $date, 'to' => $date]));

        $this->actingAs($cashier)
            ->post(route('cashier.daily-accounts.store'), [
                'occurred_on' => $date,
                'type' => DailyAccountType::OtherIncome->value,
                'category' => DailyAccountCategory::OtherIncome->value,
                'description' => 'After reopen',
                'amount' => 200,
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('daily_account_entries', [
            'description' => 'After reopen',
            'income' => 200,
        ]);
    }

    public function test_site_manager_cannot_print_daily_accounts(): void
    {
        Role::findOrCreate('site_manager');
        $site = User::factory()->create();
        $site->assignRole('site_manager');

        $this->actingAs($site)
            ->get(route('cashier.daily-accounts.print', ['date' => now()->toDateString()]))
            ->assertForbidden();
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
