<?php

namespace Tests\Feature;

use App\Enums\MaterialRequestStatus;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\MaterialRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_manager_only_sees_assigned_projects(): void
    {
        [$admin, $site, $otherSite] = $this->seedRoles();
        $customer = $this->customer();

        $assigned = Project::query()->create([
            'project_code' => 'PRJ-2026-0001',
            'name' => 'Assigned site',
            'customer_id' => $customer->id,
            'location' => 'Colombo',
            'budget' => 100000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);

        Project::query()->create([
            'project_code' => 'PRJ-2026-0002',
            'name' => 'Other site',
            'customer_id' => $customer->id,
            'location' => 'Kandy',
            'budget' => 50000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'site_manager_id' => $otherSite->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($site)
            ->get(route('construction.projects.index'))
            ->assertOk()
            ->assertSee('Assigned site')
            ->assertDontSee('Other site');

        $this->actingAs($site)
            ->get(route('construction.projects.show', $assigned))
            ->assertOk();
    }

    public function test_site_manager_cannot_approve_own_material_request(): void
    {
        [$admin, $site] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product();

        $service = app(MaterialRequestService::class);
        $request = $service->create([
            'project_id' => $project->id,
            'request_date' => now()->toDateString(),
            'required_date' => null,
        ], [
            ['product_id' => $product->id, 'quantity' => 10],
        ], $site->id);

        $service->submit($request);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot approve your own material request.');

        $service->approve($request, [
            ['id' => $request->items()->first()->id, 'quantity_approved' => 10],
        ], $site->id);
    }

    public function test_store_manager_can_approve_a_request_without_changing_stock(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product(stock: 80);

        $service = app(MaterialRequestService::class);
        $request = $service->create([
            'project_id' => $project->id,
            'request_date' => now()->toDateString(),
            'required_date' => null,
        ], [
            ['product_id' => $product->id, 'quantity' => 10],
        ], $site->id);
        $service->submit($request);

        $this->actingAs($store)
            ->post(route('store.material-requests.approve', $request), [
                'items' => [
                    ['id' => $request->items()->first()->id, 'quantity_approved' => 10],
                ],
            ])
            ->assertRedirect();

        $request->refresh();
        $product->refresh();

        $this->assertSame(MaterialRequestStatus::Approved, $request->status);
        $this->assertSame(10.0, (float) $request->items()->first()->quantity_approved);
        $this->assertSame(80.0, (float) $product->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_partial_approval_sets_partially_approved_status(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product();

        $service = app(MaterialRequestService::class);
        $request = $service->create([
            'project_id' => $project->id,
            'request_date' => now()->toDateString(),
            'required_date' => null,
        ], [
            ['product_id' => $product->id, 'quantity' => 20],
        ], $site->id);
        $service->submit($request);

        $service->approve($request, [
            ['id' => $request->items()->first()->id, 'quantity_approved' => 8],
        ], $store->id);

        $this->assertSame(MaterialRequestStatus::PartiallyApproved, $request->fresh()->status);
    }

    public function test_cashier_cannot_access_projects(): void
    {
        Role::findOrCreate('cashier');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->get(route('construction.projects.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    private function seedRoles(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('store_manager');
        Role::findOrCreate('cashier');
        Role::findOrCreate('site_manager');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $site = User::factory()->create();
        $site->assignRole('site_manager');

        $otherSite = User::factory()->create();
        $otherSite->assignRole('site_manager');

        $store = User::factory()->create();
        $store->assignRole('store_manager');

        return [$admin, $site, $otherSite, $store];
    }

    private function customer(): Customer
    {
        return Customer::query()->create(['name' => 'Kumar', 'phone' => '0771234567']);
    }

    private function project(User $admin, User $site): Project
    {
        return Project::query()->create([
            'project_code' => 'PRJ-2026-0100',
            'name' => 'Kumar Residence',
            'customer_id' => $this->customer()->id,
            'location' => 'Nugegoda',
            'budget' => 4500000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);
    }

    private function product(float $stock = 100): Product
    {
        $category = Category::query()->create(['name' => 'Cement']);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag']);

        return Product::query()->create([
            'sku' => 'CEM-001',
            'name' => 'Cement 50kg',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 2150,
            'selling_price' => 2350,
            'min_stock_level' => 50,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }
}
