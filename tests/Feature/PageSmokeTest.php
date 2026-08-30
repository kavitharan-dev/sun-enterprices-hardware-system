<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke-test key store/construction/admin pages so Blade compile bugs (e.g. @json + map)
 * and auth gaps surface as test failures instead of live 500s.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_manager_dashboard_shows_tools_and_vehicles_link(): void
    {
        $user = $this->userWithRole('store_manager');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tools &amp; vehicles', false)
            ->assertSee(route('store.assets.index'), false);
    }

    public function test_store_manager_core_pages_return_ok(): void
    {
        $user = $this->userWithRole('store_manager');
        $this->seedMinimalCatalog($user);

        $routes = [
            'dashboard',
            'store.products.index',
            'store.products.create',
            'store.suppliers.index',
            'store.suppliers.create',
            'store.customers.index',
            'store.customers.create',
            'store.purchases.index',
            'store.purchases.create',
            'store.sales.index',
            'store.sales.create',
            'store.sales.pos',
            'store.inventory.index',
            'store.inventory.movements',
            'store.categories.index',
            'store.brands.index',
            'store.units.index',
            'store.material-requests.index',
            'store.material-issues.index',
            'store.assets.index',
            'store.assets.create',
            'cashier.daily-accounts.index',
            'reports.index',
            'reports.sales',
            'reports.purchases',
            'reports.inventory',
            'reports.movements',
            'reports.outstanding',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk("Expected 200 for {$route}");
        }
    }

    public function test_cashier_core_pages_return_ok(): void
    {
        $user = $this->userWithRole('cashier');
        $this->seedMinimalCatalog($user);

        foreach ([
            'dashboard',
            'store.purchases.index',
            'store.purchases.create',
            'store.sales.index',
            'store.sales.create',
            'store.sales.pos',
            'store.inventory.index',
            'store.products.index',
            'store.assets.index',
            'cashier.daily-accounts.index',
        ] as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk("Expected 200 for {$route}");
        }
    }

    public function test_site_manager_core_pages_return_ok(): void
    {
        $admin = $this->userWithRole('admin');
        $site = $this->userWithRole('site_manager');
        $customer = Customer::query()->create(['name' => 'Owner', 'phone' => '0700000001', 'is_active' => true]);

        $project = Project::query()->create([
            'project_code' => 'PRJ-SMOKE-1',
            'name' => 'Smoke Project',
            'customer_id' => $customer->id,
            'location' => 'Colombo',
            'budget' => 100000,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);

        Worker::query()->create([
            'worker_code' => 'W-001',
            'name' => 'Worker One',
            'daily_rate' => 2500,
            'status' => 'active',
        ]);

        $this->seedMinimalCatalog($admin);

        foreach ([
            'dashboard',
            'construction.projects.index',
            'construction.workers.index',
            'construction.payroll.index',
            'construction.material-requests.index',
            'construction.material-requests.create',
            'reports.index',
            'reports.projects',
            'reports.expenses',
            'reports.issues',
        ] as $route) {
            $response = $this->actingAs($site)->get(route($route));
            $this->assertSame(200, $response->status(), "Expected 200 for {$route}, got {$response->status()}");
        }

        $this->actingAs($site)
            ->get(route('construction.projects.show', $project))
            ->assertOk();

        $this->actingAs($site)
            ->get(route('construction.projects.dashboard', $project))
            ->assertOk();
    }

    public function test_admin_core_pages_return_ok(): void
    {
        $user = $this->userWithRole('admin');

        foreach ([
            'dashboard',
            'admin.users.index',
            'admin.users.create',
            'admin.settings.edit',
            'admin.sms-logs.index',
            'admin.activity-logs.index',
        ] as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk("Expected 200 for {$route}");
        }
    }

    public function test_purchase_and_sale_show_pages_with_enum_status_return_ok(): void
    {
        $user = $this->userWithRole('store_manager');
        [$supplier, $product] = $this->seedMinimalCatalog($user);

        $purchase = Purchase::query()->create([
            'reference_no' => 'PO-SMOKE-1',
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => PurchaseStatus::Draft,
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'created_by' => $user->id,
        ]);

        $sale = Sale::query()->create([
            'invoice_no' => null,
            'customer_id' => null,
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::Draft,
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'amount_paid' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('store.purchases.index'))
            ->assertOk()
            ->assertSee('PO-SMOKE-1');

        $this->actingAs($user)
            ->get(route('store.purchases.show', $purchase))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('store.purchases.edit', $purchase))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('store.sales.show', $sale))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('store.sales.edit', $sale))
            ->assertOk();

        // unused product assert keeps PHPStan-ish unused quiet
        $this->assertNotNull($product->id);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: Supplier, 1: Product}
     */
    private function seedMinimalCatalog(User $user): array
    {
        $category = Category::query()->create(['name' => 'General', 'is_active' => true]);
        Brand::query()->create(['name' => 'Generic', 'is_active' => true]);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag', 'is_active' => true]);
        $supplier = Supplier::query()->create(['name' => 'Supplier A', 'is_active' => true]);
        Customer::query()->create(['name' => 'Walk-in List', 'phone' => '0770000000', 'is_active' => true]);

        $product = Product::query()->create([
            'sku' => 'SKU-SMOKE-1',
            'name' => 'Smoke Cement',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 2000,
            'selling_price' => 2300,
            'min_stock_level' => 5,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }
}
