<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_in_increases_stock_and_writes_a_movement(): void
    {
        $store = $this->storeManager();
        $product = $this->product(10);

        $this->actingAs($store)
            ->post(route('store.inventory.adjust'), [
                'product_id' => $product->id,
                'direction' => 'in',
                'quantity' => 5,
                'notes' => 'Count correction',
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(15.0, (float) $product->fresh()->stock_quantity);

        $movement = StockMovement::query()->first();
        $this->assertNotNull($movement);
        $this->assertSame(MovementType::AdjustmentIn, $movement->movement_type);
        $this->assertSame(5.0, (float) $movement->quantity);
        $this->assertSame(15.0, (float) $movement->balance_after);
        $this->assertSame('Count correction', $movement->notes);
    }

    public function test_adjustment_out_is_blocked_when_stock_is_insufficient(): void
    {
        $store = $this->storeManager();
        $product = $this->product(3);

        $this->actingAs($store)
            ->from(route('store.inventory.index'))
            ->post(route('store.inventory.adjust'), [
                'product_id' => $product->id,
                'direction' => 'out',
                'quantity' => 10,
                'notes' => 'Damaged',
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(3.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_adjustment_requires_a_reason(): void
    {
        $store = $this->storeManager();
        $product = $this->product();

        $this->actingAs($store)
            ->from(route('store.inventory.index'))
            ->post(route('store.inventory.adjust'), [
                'product_id' => $product->id,
                'direction' => 'in',
                'quantity' => 1,
                'notes' => '',
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('notes');
    }

    private function storeManager(): User
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('store_manager');
        $user = User::factory()->create();
        $user->assignRole('store_manager');

        return $user;
    }

    private function product(float $stock = 10): Product
    {
        $category = Category::query()->create(['name' => 'Cement']);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag']);

        return Product::query()->create([
            'sku' => 'CEM-ADJ',
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
