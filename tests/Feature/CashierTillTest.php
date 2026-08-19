<?php

namespace Tests\Feature;

use App\Enums\CashierRequestStatus;
use App\Enums\DailyAccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Enums\SaleStatus;
use App\Models\CashierRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyAccountEntry;
use App\Models\Product;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierTillTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_manager_sale_does_not_move_stock_until_cashier_confirms(): void
    {
        [$store, $cashier, $product] = $this->shopUsers();

        $sale = app(SaleService::class)->create([
            'customer_id' => null,
            'walk_in_name' => 'Nimal',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2350],
        ], $store->id);

        $this->actingAs($store)
            ->post(route('store.sales.complete', $sale), [
                'payment_method' => 'cash',
                'payment_amount' => 4700,
            ])
            ->assertRedirect(route('store.sales.show', $sale));

        $this->assertTrue($sale->fresh()->isDraft());
        $this->assertSame(100.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame(0, DailyAccountEntry::query()->count());

        $pending = CashierRequest::query()->pending()->first();
        $this->assertNotNull($pending);
        $this->assertSame(4700.0, (float) $pending->amount);

        $this->actingAs($store)
            ->post(route('cashier.requests.confirm', $pending), [
                'payment_date' => now()->toDateString(),
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertForbidden();

        $this->actingAs($cashier)
            ->post(route('cashier.requests.confirm', $pending), [
                'payment_date' => now()->toDateString(),
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect();

        $this->assertSame(SaleStatus::Completed, $sale->fresh()->status);
        $this->assertSame(98.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::Sale->value,
            'income' => 4700,
        ]);
        $this->assertSame(CashierRequestStatus::Confirmed, $pending->fresh()->status);
    }

    public function test_cashier_completing_a_sale_records_money_immediately(): void
    {
        [, $cashier, $product] = $this->shopUsers();

        $sale = app(SaleService::class)->create([
            'customer_id' => null,
            'walk_in_name' => 'Counter',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2350],
        ], $cashier->id);

        $this->actingAs($cashier)
            ->post(route('store.sales.complete', $sale), [
                'payment_method' => 'cash',
                'payment_amount' => 2350,
            ])
            ->assertRedirect();

        $this->assertTrue($sale->fresh()->isCompleted());
        $this->assertSame(99.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame(0, CashierRequest::query()->pending()->count());
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::Sale->value,
            'income' => 2350,
        ]);
    }

    public function test_store_manager_purchase_does_not_increase_stock_until_cashier_confirms(): void
    {
        [$store, $cashier, $product] = $this->shopUsers();
        $supplier = Supplier::query()->create(['name' => 'Ceylon Cement']);

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 2000],
        ], $store->id);

        $this->actingAs($store)
            ->post(route('store.purchases.complete', $purchase))
            ->assertRedirect(route('store.purchases.show', $purchase));

        $this->assertTrue($purchase->fresh()->isDraft());
        $this->assertSame(100.0, (float) $product->fresh()->stock_quantity);

        $pending = CashierRequest::query()->pending()->first();
        $this->assertSame(20000.0, (float) $pending->amount);

        $this->actingAs($cashier)
            ->post(route('cashier.requests.confirm', $pending), [
                'payment_date' => now()->toDateString(),
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect();

        $this->assertTrue($purchase->fresh()->isCompleted());
        $this->assertSame(110.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::Purchase->value,
            'expense' => 20000,
        ]);
    }

    public function test_site_owner_payment_does_not_change_project_totals_until_confirmed(): void
    {
        [$admin, $site] = $this->constructionUsers();
        $project = $this->project($site, 4500000);

        $this->actingAs($site)
            ->post(route('construction.projects.owner-payments.store', $project), [
                'amount' => 500000,
                'payment_date' => now()->toDateString(),
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $project->fresh()->totalReceived());
        $this->assertSame(4500000.0, $project->fresh()->remainingToReceive());
        $this->assertSame(0, DailyAccountEntry::query()->count());

        $this->confirmPendingCashierRequests($admin);

        $this->assertSame(500000.0, $project->fresh()->totalReceived());
        $this->assertSame(4000000.0, $project->fresh()->remainingToReceive());
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::OwnerPayment->value,
            'income' => 500000,
        ]);
    }

    public function test_site_expense_does_not_increase_spent_until_cashier_pays(): void
    {
        [$admin, $site] = $this->constructionUsers();
        $project = $this->project($site, 100000);

        $this->actingAs($site)
            ->post(route('construction.projects.expenses.store', $project), [
                'category' => ExpenseCategory::Transport->value,
                'amount' => 4000,
                'expense_date' => now()->toDateString(),
                'description' => 'Lorry hire',
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $project->fresh()->totalSpent());

        $this->confirmPendingCashierRequests($admin);

        $this->assertSame(4000.0, $project->fresh()->totalSpent());
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::ProjectExpense->value,
            'expense' => 4000,
        ]);
    }

    public function test_daily_accounts_lists_pending_requests_for_the_cashier(): void
    {
        [$store, $cashier, $product] = $this->shopUsers();

        $sale = app(SaleService::class)->create([
            'customer_id' => null,
            'walk_in_name' => 'Queue',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2350],
        ], $store->id);

        $this->actingAs($store)->post(route('store.sales.complete', $sale), [
            'payment_method' => 'cash',
            'payment_amount' => 2350,
        ]);

        $this->actingAs($cashier)
            ->get(route('cashier.daily-accounts.index'))
            ->assertOk()
            ->assertSee('Awaiting cashier')
            ->assertSee('Hardware sale payment');

        $this->actingAs($store)
            ->get(route('cashier.daily-accounts.index'))
            ->assertOk()
            ->assertSee('Waiting for cashier');
    }

    /**
     * @return array{0: User, 1: User, 2: Product}
     */
    private function shopUsers(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('store_manager');
        Role::findOrCreate('cashier');

        $store = User::factory()->create();
        $store->assignRole('store_manager');

        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $category = Category::query()->create(['name' => 'Cement']);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag']);
        $product = Product::query()->create([
            'sku' => 'CEM-TILL-1',
            'name' => 'Cement 50kg',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 2000,
            'selling_price' => 2350,
            'min_stock_level' => 10,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        return [$store, $cashier, $product];
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

    private function project(User $site, float $budget): Project
    {
        return Project::query()->create([
            'project_code' => 'PRJ-TILL-1',
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
