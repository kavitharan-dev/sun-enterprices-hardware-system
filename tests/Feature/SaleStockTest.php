<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaleStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_sale_reduces_inventory_and_writes_a_sale_out_movement(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 100);

        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => null,
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 2350],
        ], $user->id);

        $this->assertTrue($sale->isDraft());
        $this->assertSame(100.0, (float) $product->fresh()->stock_quantity);

        $service->complete($sale, [
            'amount' => 23500,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $user->id);

        $product->refresh();
        $sale->refresh();

        $this->assertSame(SaleStatus::Completed, $sale->status);
        $this->assertSame(PaymentStatus::Paid, $sale->payment_status);
        $this->assertNotNull($sale->invoice_no);
        $this->assertSame(90.0, (float) $product->stock_quantity);

        $movement = StockMovement::query()->where('movement_type', MovementType::SaleOut)->first();

        $this->assertNotNull($movement);
        $this->assertSame(-10.0, (float) $movement->quantity);
        $this->assertSame(90.0, (float) $movement->balance_after);
        $this->assertSame(Sale::class, $movement->reference_type);
        $this->assertSame($sale->id, $movement->reference_id);
    }

    public function test_completing_a_sale_is_blocked_when_stock_is_insufficient(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 2);

        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => null,
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 2350],
        ], $user->id);

        try {
            $service->complete($sale, [
                'amount' => 100,
                'method' => 'cash',
            ], $user->id);
            $this->fail('Expected insufficient stock to block the sale.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(2.0, (float) $product->fresh()->stock_quantity);
        $this->assertTrue($sale->fresh()->isDraft());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_partial_payment_leaves_a_balance_and_later_payment_clears_it(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 50);
        $customer = Customer::query()->create(['name' => 'Kumar', 'phone' => '0771234567']);

        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
        ], $user->id);

        $service->complete($sale, [
            'amount' => 500,
            'method' => 'cash',
        ], $user->id);

        $sale->refresh();
        $customer->refresh();

        $this->assertSame(PaymentStatus::Partial, $sale->payment_status);
        $this->assertSame(1500.0, (float) $sale->balance);
        $this->assertSame(1500.0, (float) $customer->outstanding_balance);

        $service->recordPayment($sale, [
            'amount' => 1500,
            'payment_method' => 'card',
            'payment_date' => now()->toDateString(),
        ], $user->id);

        $sale->refresh();
        $customer->refresh();

        $this->assertSame(PaymentStatus::Paid, $sale->payment_status);
        $this->assertSame(0.0, (float) $sale->balance);
        $this->assertSame(0.0, (float) $customer->outstanding_balance);
    }

    public function test_cashier_can_access_sales_customers_and_inventory(): void
    {
        Role::findOrCreate('cashier');

        $user = User::factory()->create();
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(route('store.sales.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('store.customers.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('store.products.create'))
            ->assertOk();
    }

    public function test_walk_in_name_appears_on_bill_and_print_pages(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 20);

        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => null,
            'walk_in_name' => 'Nimal Perera',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2350],
        ], $user->id);

        $service->complete($sale, [
            'amount' => 2350,
            'method' => 'cash',
        ], $user->id);

        $sale->refresh();

        $this->actingAs($user)
            ->get(route('store.sales.bill', $sale))
            ->assertOk()
            ->assertSee('SUN ENTERPRICES')
            ->assertSee('Nilaweli, Trincomalee')
            ->assertSee('Hardware Store & Construction')
            ->assertSee('Nimal Perera')
            ->assertSee($sale->invoice_no)
            ->assertSee('Customer paid')
            ->assertSee('Balance due');

        $this->actingAs($user)
            ->get(route('store.sales.print', $sale))
            ->assertOk()
            ->assertSee('SUN ENTERPRICES')
            ->assertSee('Nimal Perera')
            ->assertSee('New sale');

        $this->actingAs($user)
            ->get(route('store.sales.thermal', $sale))
            ->assertOk()
            ->assertSee('SUN ENTERPRICES')
            ->assertSee('Nimal Perera')
            ->assertSee('Receipt');

        $this->actingAs($user)
            ->get(route('store.sales.pos'))
            ->assertOk()
            ->assertSee('Barcode / SKU')
            ->assertSee('Point of Sale')
            ->assertSee('Exit POS')
            ->assertDontSee('lg:static lg:inset-auto lg:flex lg:flex-col lg:shrink-0', false);
    }

    public function test_cash_sale_cannot_complete_without_paid_amount(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 20);
        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => null,
            'walk_in_name' => 'Cashier Test',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2387],
        ], $user->id);

        try {
            $service->complete($sale, ['method' => 'cash'], $user->id);
            $this->fail('Expected cash sales to require a paid amount.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('amount the customer paid', $e->getMessage());
        }

        $this->assertTrue($sale->fresh()->isDraft());
        $this->assertSame(20.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_credit_sale_can_complete_without_paid_amount(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 20);
        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => null,
            'walk_in_name' => 'Credit Customer',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2387],
        ], $user->id);

        $service->complete($sale, ['method' => 'credit'], $user->id);
        $sale->refresh();

        $this->assertTrue($sale->isCompleted());
        $this->assertSame(0.0, (float) $sale->paid_amount);
        $this->assertSame(2387.0, (float) $sale->balance);
        $this->assertSame(0.0, (float) $sale->change_amount);
    }

    public function test_cash_overpay_records_change_and_zero_balance(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 20);
        $service = app(SaleService::class);

        $sale = $service->create([
            'customer_id' => null,
            'walk_in_name' => 'Cash Customer',
            'sale_date' => now()->toDateString(),
            'discount' => 0,
            'tax' => 0,
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2387],
        ], $user->id);

        $service->complete($sale, [
            'amount' => 5000,
            'method' => 'cash',
        ], $user->id);

        $sale->refresh();

        $this->assertSame(2387.0, (float) $sale->total);
        $this->assertSame(5000.0, (float) $sale->tendered_amount);
        $this->assertSame(2387.0, (float) $sale->paid_amount);
        $this->assertSame(2613.0, (float) $sale->change_amount);
        $this->assertSame(0.0, (float) $sale->balance);

        $this->actingAs($user)
            ->get(route('store.sales.bill', $sale))
            ->assertOk()
            ->assertSee('5,000.00')
            ->assertSee('2,613.00')
            ->assertSee('Change to return');
    }

    public function test_cashier_cannot_complete_sale_form_without_paid_amount(): void
    {
        [$user, $product] = $this->seedSaleCatalog(stock: 20);

        $this->actingAs($user)
            ->from(route('store.sales.create'))
            ->post(route('store.sales.store'), [
                'walk_in_name' => 'Counter Customer',
                'sale_date' => now()->toDateString(),
                'discount' => 0,
                'tax' => 0,
                'payment_method' => 'cash',
                'complete' => '1',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_price' => 2387,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('store.sales.create'))
            ->assertSessionHasErrors('payment_amount');

        $this->assertSame(0, \App\Models\Sale::query()->count());
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function seedSaleCatalog(float $stock): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('store_manager');
        Role::findOrCreate('cashier');

        $user = User::factory()->create();
        $user->assignRole('admin');

        $category = Category::query()->create(['name' => 'Cement & Concrete']);
        $unit = Unit::query()->create(['name' => 'Bag', 'symbol' => 'bag']);

        $product = Product::query()->create([
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

        return [$user, $product];
    }
}
