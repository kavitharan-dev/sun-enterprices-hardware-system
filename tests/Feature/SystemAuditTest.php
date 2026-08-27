<?php

namespace Tests\Feature;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\MaterialRequestStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\WorkerStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyAccountDay;
use App\Models\DailyAccountEntry;
use App\Models\MaterialIssue;
use App\Models\MaterialRequest;
use App\Models\Product;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deep system audit: remaining show/edit GETs, HTTP happy-path workflows,
 * invoices, material-request lifecycle, payroll settlement via till, and role negatives.
 */
class SystemAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_catalog_show_edit_and_create_pages_return_ok(): void
    {
        $user = $this->roleUser('store_manager');
        [$category, $brand, $unit, $supplier, $customer, $product] = $this->catalog();

        $routes = [
            ['store.categories.create'],
            ['store.categories.edit', $category],
            ['store.brands.create'],
            ['store.brands.edit', $brand],
            ['store.units.create'],
            ['store.units.edit', $unit],
            ['store.products.show', $product],
            ['store.products.edit', $product],
            ['store.suppliers.show', $supplier],
            ['store.suppliers.edit', $supplier],
            ['store.customers.show', $customer],
            ['store.customers.edit', $customer],
        ];

        foreach ($routes as $args) {
            $name = array_shift($args);
            $response = $this->actingAs($user)->get(route($name, $args));
            $this->assertSame(200, $response->status(), "Expected 200 for {$name}");
        }
    }

    public function test_admin_can_open_user_edit_and_project_create_edit(): void
    {
        $admin = $this->roleUser('admin');
        $site = $this->roleUser('site_manager');
        $customer = Customer::query()->create(['name' => 'Owner', 'is_active' => true]);

        $project = Project::query()->create([
            'project_code' => 'PRJ-AUDIT-1',
            'name' => 'Audit Project',
            'customer_id' => $customer->id,
            'location' => 'Colombo',
            'budget' => 100000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);

        $worker = Worker::query()->create([
            'worker_code' => 'W-AUDIT-1',
            'name' => 'Audit Worker',
            'daily_rate' => 2500,
            'weekly_salary' => 15000,
            'status' => WorkerStatus::Active,
        ]);

        foreach ([
            ['admin.users.edit', $admin],
            ['construction.projects.create'],
            ['construction.projects.edit', $project],
            ['construction.workers.create'],
            ['construction.workers.show', $worker],
            ['construction.workers.edit', $worker],
            ['construction.workers.payroll', $worker],
        ] as $args) {
            $name = array_shift($args);
            $response = $this->actingAs($admin)->get(route($name, $args));
            $this->assertSame(200, $response->status(), "Expected 200 for {$name}");
        }

        $this->actingAs($site)
            ->get(route('construction.projects.create'))
            ->assertForbidden();
    }

    public function test_http_purchase_draft_then_cashier_pays_and_stock_increases(): void
    {
        $store = $this->roleUser('store_manager');
        $cashier = $this->roleUser('cashier');
        [, , , $supplier, , $product] = $this->catalog(stock: 40);

        $this->actingAs($store)
            ->post(route('store.purchases.store'), [
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'discount' => 0,
                'tax' => 0,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 2000],
                ],
            ])
            ->assertRedirect();

        $purchase = Purchase::query()->latest('id')->first();
        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->isDraft());
        $this->assertSame(40.0, (float) $product->fresh()->stock_quantity);

        $this->actingAs($store)
            ->get(route('store.purchases.edit', $purchase))
            ->assertOk();

        $this->cashierRecords([
            'occurred_on' => now()->toDateString(),
            'type' => DailyAccountType::Purchase->value,
            'purchase_id' => $purchase->id,
            'method' => PaymentMethod::Cash->value,
        ], $cashier);

        $this->assertSame(PurchaseStatus::Completed, $purchase->fresh()->status);
        $this->assertSame(45.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::Purchase->value,
            'expense' => 10000,
        ]);
    }

    public function test_http_sale_complete_prints_invoice_bill_and_thermal(): void
    {
        $cashier = $this->roleUser('cashier');
        [, , , , , $product] = $this->catalog(stock: 20);

        $this->actingAs($cashier)
            ->post(route('store.sales.store'), [
                'walk_in_name' => 'Walk-in Audit',
                'sale_date' => now()->toDateString(),
                'discount' => 0,
                'tax' => 0,
                'complete' => 1,
                'payment_method' => 'cash',
                'payment_amount' => 2300,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2300, 'discount' => 0],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::query()->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame(SaleStatus::Completed, $sale->status);
        $this->assertSame(19.0, (float) $product->fresh()->stock_quantity);

        foreach ([
            'store.sales.bill',
            'store.sales.print',
            'store.sales.thermal',
            'store.sales.invoice',
            'store.sales.invoice.download',
        ] as $routeName) {
            $response = $this->actingAs($cashier)->get(route($routeName, $sale));
            $this->assertSame(200, $response->status(), "Expected 200 for {$routeName}");
        }
    }

    public function test_material_request_create_submit_reject_and_approve_issue_show_pages(): void
    {
        $admin = $this->roleUser('admin');
        $site = $this->roleUser('site_manager');
        $store = $this->roleUser('store_manager');
        [, , , , $customer, $product] = $this->catalog(stock: 50);

        $project = Project::query()->create([
            'project_code' => 'PRJ-MR-1',
            'name' => 'MR Project',
            'customer_id' => $customer->id,
            'location' => 'Galle',
            'budget' => 50000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($site)
            ->post(route('construction.material-requests.store'), [
                'project_id' => $project->id,
                'request_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 4, 'notes' => ''],
                ],
                'submit' => 1,
            ])
            ->assertRedirect();

        $request = MaterialRequest::query()->latest('id')->first();
        $this->assertNotNull($request);
        $this->assertSame(MaterialRequestStatus::Pending, $request->status);

        $this->actingAs($site)
            ->get(route('construction.material-requests.show', $request))
            ->assertOk();

        $this->actingAs($store)
            ->get(route('store.material-requests.show', $request))
            ->assertOk();

        $this->actingAs($store)
            ->post(route('store.material-requests.reject', $request), [
                'rejection_reason' => 'Not needed this week',
            ])
            ->assertRedirect();

        $this->assertSame(MaterialRequestStatus::Rejected, $request->fresh()->status);

        $this->actingAs($site)
            ->post(route('construction.material-requests.store'), [
                'project_id' => $project->id,
                'request_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3, 'notes' => ''],
                ],
                'submit' => 1,
            ])
            ->assertRedirect();

        $approved = MaterialRequest::query()->latest('id')->first();
        $this->assertSame(MaterialRequestStatus::Pending, $approved->status);

        $this->actingAs($store)
            ->post(route('store.material-requests.approve', $approved), [
                'items' => [
                    ['id' => $approved->items()->first()->id, 'quantity_approved' => 3],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($store)
            ->post(route('store.material-requests.issue', $approved), [
                'issue_date' => now()->toDateString(),
                'items' => [
                    ['id' => $approved->items()->first()->id, 'quantity' => 3],
                ],
            ])
            ->assertRedirect();

        $issue = MaterialIssue::query()->latest('id')->first();
        $this->assertNotNull($issue);
        $this->assertSame(47.0, (float) $product->fresh()->stock_quantity);

        $this->actingAs($store)
            ->get(route('store.material-issues.show', $issue))
            ->assertOk();
    }

    public function test_worker_settlement_and_opening_balance_via_daily_accounts(): void
    {
        $cashier = $this->roleUser('cashier');
        $worker = Worker::query()->create([
            'worker_code' => 'W-PAY-1',
            'name' => 'Pay Worker',
            'daily_rate' => 2000,
            'weekly_salary' => 10000,
            'status' => WorkerStatus::Active,
        ]);

        $this->cashierRecords([
            'occurred_on' => now()->toDateString(),
            'type' => DailyAccountType::WorkerSettlement->value,
            'worker_id' => $worker->id,
            'amount' => 10000,
            'method' => PaymentMethod::Cash->value,
            'debt_deducted' => 0,
        ], $cashier);

        $this->assertDatabaseHas('daily_account_entries', [
            'type' => DailyAccountType::WorkerSettlement->value,
            'expense' => 10000,
        ]);

        $this->actingAs($cashier)
            ->put(route('cashier.daily-accounts.opening'), [
                'business_date' => now()->toDateString(),
                'opening_balance' => 5000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $day = DailyAccountDay::query()->whereDate('business_date', now()->toDateString())->first();
        $this->assertNotNull($day);
        $this->assertSame(5000.0, (float) $day->opening_balance);
    }

    public function test_product_can_be_created_over_http_and_found_by_name_search(): void
    {
        $user = $this->roleUser('store_manager');
        [$category, , $unit] = $this->catalog();

        $this->actingAs($user)
            ->post(route('store.products.store'), [
                'sku' => 'NEW-CEM-99',
                'name' => 'Ultra Cement Bag',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'purchase_price' => 2100,
                'selling_price' => 2400,
                'min_stock_level' => 10,
                'opening_stock' => 12,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'sku' => 'NEW-CEM-99',
            'name' => 'Ultra Cement Bag',
        ]);

        $this->actingAs($user)
            ->get(route('store.inventory.index', ['q' => 'ultra cement']))
            ->assertOk()
            ->assertSee('Ultra Cement Bag');
    }

    public function test_role_negatives_for_wrong_modules(): void
    {
        $cashier = $this->roleUser('cashier');
        $site = $this->roleUser('site_manager');
        $store = $this->roleUser('store_manager');

        $this->actingAs($cashier)
            ->get(route('reports.projects'))
            ->assertForbidden();

        $this->actingAs($site)
            ->get(route('reports.sales'))
            ->assertForbidden();

        $this->actingAs($site)
            ->get(route('store.purchases.create'))
            ->assertForbidden();

        $this->actingAs($store)
            ->post(route('cashier.daily-accounts.store'), [
                'occurred_on' => now()->toDateString(),
                'type' => DailyAccountType::OtherIncome->value,
                'amount' => 100,
                'method' => PaymentMethod::Cash->value,
                'category' => DailyAccountCategory::OtherIncome->value,
                'description' => 'Should fail',
            ])
            ->assertForbidden();
    }

    public function test_partial_sale_payment_and_manual_entry_destroy(): void
    {
        $cashier = $this->roleUser('cashier');
        [, , , , , $product] = $this->catalog(stock: 30);

        $sale = app(SaleService::class)->create([
            'walk_in_name' => 'Credit Customer',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2300],
        ], $cashier->id);

        app(SaleService::class)->complete($sale, [
            'amount' => 2000,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $cashier->id);

        $sale->refresh();
        $this->assertGreaterThan(0, (float) $sale->balance);
        $this->assertSame(PaymentStatus::Partial, $sale->payment_status);

        $this->actingAs($cashier)
            ->post(route('store.sales.pay', $sale), [
                'amount' => $sale->balance,
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(0.0, (float) $sale->fresh()->balance);
        $this->assertSame(PaymentStatus::Paid, $sale->fresh()->payment_status);

        $this->cashierRecords([
            'occurred_on' => now()->toDateString(),
            'type' => DailyAccountType::OtherExpense->value,
            'amount' => 250,
            'method' => PaymentMethod::Cash->value,
            'category' => DailyAccountCategory::Other->value,
            'description' => 'Tea for staff',
        ], $cashier);

        $entry = DailyAccountEntry::query()
            ->where('type', DailyAccountType::OtherExpense->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertTrue((bool) $entry->is_manual);

        $this->actingAs($cashier)
            ->delete(route('cashier.daily-accounts.destroy', $entry))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('daily_account_entries', ['id' => $entry->id]);
    }

    public function test_notifications_can_be_marked_read(): void
    {
        $user = $this->roleUser('admin');

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['message' => 'Audit ping'];
            }
        });

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();
    }

    private function roleUser(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: Category, 1: Brand, 2: Unit, 3: Supplier, 4: Customer, 5: Product}
     */
    private function catalog(float $stock = 50): array
    {
        $category = Category::query()->create(['name' => 'Audit Cat', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Audit Brand', 'is_active' => true]);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag', 'is_active' => true]);
        $supplier = Supplier::query()->create(['name' => 'Audit Supplier', 'is_active' => true]);
        $customer = Customer::query()->create(['name' => 'Audit Customer', 'phone' => '0771111111', 'is_active' => true]);
        $product = Product::query()->create([
            'sku' => 'AUDIT-SKU-1',
            'name' => 'Audit Cement',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'purchase_price' => 2000,
            'selling_price' => 2300,
            'min_stock_level' => 5,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        return [$category, $brand, $unit, $supplier, $customer, $product];
    }
}
