<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\MaterialRequestStatus;
use App\Enums\MovementType;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\MaterialIssueService;
use App\Services\MaterialRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_materials_reduces_stock_and_posts_a_project_expense(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product(stock: 100);
        $request = $this->approvedRequest($site, $store, $project, $product, requested: 10, approved: 10);

        $this->actingAs($store)
            ->post(route('store.material-requests.issue', $request), [
                'issue_date' => now()->toDateString(),
                'items' => [
                    ['id' => $request->items()->first()->id, 'quantity' => 10],
                ],
            ])
            ->assertRedirect();

        $product->refresh();
        $request->refresh();

        $this->assertSame(90.0, (float) $product->stock_quantity);
        $this->assertSame(MaterialRequestStatus::Issued, $request->status);
        $this->assertSame(10.0, (float) $request->items()->first()->quantity_issued);

        $issue = MaterialIssue::query()->first();
        $this->assertNotNull($issue);
        $this->assertSame(21500.0, (float) $issue->total_cost);

        $movement = StockMovement::query()->first();
        $this->assertSame(MovementType::MaterialIssueOut, $movement->movement_type);
        $this->assertSame(-10.0, (float) $movement->quantity);
        $this->assertSame(MaterialIssue::class, $movement->reference_type);
        $this->assertSame($issue->id, $movement->reference_id);

        $expense = ProjectExpense::query()->first();
        $this->assertNotNull($expense);
        $this->assertSame(ExpenseCategory::Material, $expense->category);
        $this->assertSame(21500.0, (float) $expense->amount);
        $this->assertSame($project->id, $expense->project_id);
        $this->assertSame($issue->id, $expense->reference_id);
        $this->assertStringContainsString('Cement 50kg', $expense->description);

        $this->actingAs($admin)
            ->get(route('construction.projects.show', $project))
            ->assertOk()
            ->assertSee($request->request_no)
            ->assertSee('Cement 50kg');
    }

    public function test_cannot_issue_more_than_remaining_approved_quantity(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product();
        $request = $this->approvedRequest($site, $store, $project, $product, requested: 10, approved: 4);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot issue more than the remaining approved quantity');

        app(MaterialIssueService::class)->issueFromRequest($request, [
            ['id' => $request->items()->first()->id, 'quantity' => 5],
        ], $store->id);
    }

    public function test_cannot_issue_more_than_available_stock(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product(stock: 2);
        $request = $this->approvedRequest($site, $store, $project, $product, requested: 10, approved: 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        app(MaterialIssueService::class)->issueFromRequest($request, [
            ['id' => $request->items()->first()->id, 'quantity' => 5],
        ], $store->id);
    }

    public function test_partial_issue_sets_partially_issued_then_completes(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product(stock: 50);
        $request = $this->approvedRequest($site, $store, $project, $product, requested: 10, approved: 10);
        $service = app(MaterialIssueService::class);

        $service->issueFromRequest($request, [
            ['id' => $request->items()->first()->id, 'quantity' => 4],
        ], $store->id);

        $this->assertSame(MaterialRequestStatus::PartiallyIssued, $request->fresh()->status);
        $this->assertSame(46.0, (float) $product->fresh()->stock_quantity);

        $service->issueFromRequest($request->fresh(), [
            ['id' => $request->items()->first()->id, 'quantity' => 6],
        ], $store->id);

        $this->assertSame(MaterialRequestStatus::Issued, $request->fresh()->status);
        $this->assertSame(40.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame(2, MaterialIssue::query()->count());
        $this->assertSame(2, ProjectExpense::query()->count());
    }

    public function test_site_manager_cannot_issue_materials(): void
    {
        [$admin, $site, , $store] = $this->seedRoles();
        $project = $this->project($admin, $site);
        $product = $this->product();
        $request = $this->approvedRequest($site, $store, $project, $product, requested: 5, approved: 5);

        $this->actingAs($site)
            ->post(route('store.material-requests.issue', $request), [
                'issue_date' => now()->toDateString(),
                'items' => [
                    ['id' => $request->items()->first()->id, 'quantity' => 5],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(100.0, (float) $product->fresh()->stock_quantity);
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
        $other = User::factory()->create();
        $other->assignRole('site_manager');
        $store = User::factory()->create();
        $store->assignRole('store_manager');

        return [$admin, $site, $other, $store];
    }

    private function project(User $admin, User $site): Project
    {
        return Project::query()->create([
            'project_code' => 'PRJ-2026-0200',
            'name' => 'Kumar Residence',
            'customer_id' => Customer::query()->create(['name' => 'Kumar'])->id,
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
            'sku' => 'CEM-ISSUE',
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

    private function approvedRequest(User $site, User $store, Project $project, Product $product, float $requested, float $approved)
    {
        $service = app(MaterialRequestService::class);
        $request = $service->create([
            'project_id' => $project->id,
            'request_date' => now()->toDateString(),
            'required_date' => null,
        ], [
            ['product_id' => $product->id, 'quantity' => $requested],
        ], $site->id);

        $service->submit($request);
        $service->approve($request, [
            ['id' => $request->items()->first()->id, 'quantity_approved' => $approved],
        ], $store->id);

        return $request->fresh(['items.product']);
    }
}
