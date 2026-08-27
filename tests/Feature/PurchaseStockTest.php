<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_purchase_increases_inventory_and_writes_a_stock_movement(): void
    {
        Role::findOrCreate('admin');

        $user = User::factory()->create();
        $user->assignRole('admin');

        $category = Category::query()->create(['name' => 'Cement & Concrete']);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag']);
        $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

        $product = Product::query()->create([
            'sku' => 'CEM-001',
            'name' => 'Cement 50kg',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 2150,
            'selling_price' => 2350,
            'min_stock_level' => 50,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        $service = app(PurchaseService::class);

        $purchase = $service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
            'notes' => 'Test receipt',
        ], [
            ['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 2150],
        ], $user->id);

        $this->assertTrue($purchase->isDraft());
        $this->assertSame(0.0, (float) $product->fresh()->stock_quantity);

        $service->complete($purchase, $user->id);

        $product->refresh();
        $purchase->refresh();

        $this->assertSame(PurchaseStatus::Completed, $purchase->status);
        $this->assertSame(100.0, (float) $product->stock_quantity);

        $movement = StockMovement::query()->first();

        $this->assertNotNull($movement);
        $this->assertSame(MovementType::PurchaseIn, $movement->movement_type);
        $this->assertSame(100.0, (float) $movement->quantity);
        $this->assertSame(100.0, (float) $movement->balance_after);
        $this->assertSame(Purchase::class, $movement->reference_type);
        $this->assertSame($purchase->id, $movement->reference_id);
    }

    public function test_store_manager_can_view_inventory(): void
    {
        Role::findOrCreate('store_manager');

        $user = User::factory()->create();
        $user->assignRole('store_manager');

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk();
    }

    public function test_cashier_can_access_inventory(): void
    {
        Role::findOrCreate('cashier');

        $user = User::factory()->create();
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk();
    }

    public function test_inventory_search_matches_product_name_case_insensitively(): void
    {
        Role::findOrCreate('cashier');

        $user = User::factory()->create();
        $user->assignRole('cashier');

        $category = Category::query()->create(['name' => 'Paint']);
        $unit = Unit::query()->create(['name' => 'Tin', 'symbol' => 'tin']);

        Product::query()->create([
            'sku' => '8901234567890',
            'name' => 'Asian Paint White',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 1000,
            'selling_price' => 1200,
            'min_stock_level' => 2,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Product::query()->create([
            'sku' => '9999999999999',
            'name' => 'Other Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 100,
            'selling_price' => 150,
            'min_stock_level' => 1,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('store.inventory.index', ['q' => 'asian paint']))
            ->assertOk()
            ->assertSee('Asian Paint White')
            ->assertSee('8901234567890')
            ->assertDontSee('Other Product');

        $this->actingAs($user)
            ->get(route('store.inventory.index', ['q' => '8901234567890']))
            ->assertOk()
            ->assertSee('Asian Paint White');
    }
}