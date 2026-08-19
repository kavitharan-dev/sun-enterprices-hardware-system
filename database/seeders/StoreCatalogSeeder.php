<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Cement & Concrete',
            'Steel & Metal',
            'Bricks & Blocks',
            'Sand & Aggregates',
            'Plumbing',
            'Electrical',
            'Paint & Finishing',
            'Tools',
            'Hardware Fasteners',
        ];

        foreach ($categories as $index => $name) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => $index + 1]
            );
        }

        foreach (['Holcim', 'Tokyo Cement', 'Lanwa', 'S-Lon', 'Nippon', 'Bosch', 'Generic'] as $index => $name) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => $index + 1]
            );
        }

        $units = [
            ['name' => 'Bag', 'symbol' => 'bag'],
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Meter', 'symbol' => 'm'],
            ['name' => 'Piece', 'symbol' => 'pcs'],
            ['name' => 'Cubic metre', 'symbol' => 'm³'],
            ['name' => 'Litre', 'symbol' => 'L'],
            ['name' => 'Box', 'symbol' => 'box'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['symbol' => $unit['symbol']], $unit + ['is_active' => true]);
        }

        $suppliers = [
            ['name' => 'Lanka Building Materials', 'contact_person' => 'Nimal Perera', 'phone' => '0112345001', 'address' => 'Peliyagoda'],
            ['name' => 'Colombo Steel Traders', 'contact_person' => 'Ruwan Silva', 'phone' => '0112345002', 'address' => 'Grandpass'],
            ['name' => 'Island Hardware Supplies', 'contact_person' => 'Ayesha Fernando', 'phone' => '0112345003', 'address' => 'Nugegoda'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(['name' => $supplier['name']], $supplier + ['is_active' => true]);
        }

        $bag = Unit::query()->where('symbol', 'bag')->first();
        $kg = Unit::query()->where('symbol', 'kg')->first();
        $pcs = Unit::query()->where('symbol', 'pcs')->first();
        $m = Unit::query()->where('symbol', 'm')->first();
        $cubic = Unit::query()->where('symbol', 'm³')->first();
        $litre = Unit::query()->where('symbol', 'L')->first();
        $box = Unit::query()->where('symbol', 'box')->first();

        $products = [
            ['sku' => 'CEM-001', 'name' => 'Cement 50kg', 'category' => 'Cement & Concrete', 'brand' => 'Tokyo Cement', 'unit' => $bag, 'purchase_price' => 2150, 'selling_price' => 2350, 'min_stock_level' => 50],
            ['sku' => 'STE-001', 'name' => 'Steel TMT Bar 12mm', 'category' => 'Steel & Metal', 'brand' => 'Lanwa', 'unit' => $kg, 'purchase_price' => 285, 'selling_price' => 320, 'min_stock_level' => 200],
            ['sku' => 'BRK-001', 'name' => 'Clay Brick', 'category' => 'Bricks & Blocks', 'brand' => 'Generic', 'unit' => $pcs, 'purchase_price' => 28, 'selling_price' => 35, 'min_stock_level' => 1000],
            ['sku' => 'SND-001', 'name' => 'River Sand', 'category' => 'Sand & Aggregates', 'brand' => 'Generic', 'unit' => $cubic, 'purchase_price' => 8500, 'selling_price' => 9800, 'min_stock_level' => 5],
            ['sku' => 'PVC-001', 'name' => 'PVC Pipe 1 inch', 'category' => 'Plumbing', 'brand' => 'S-Lon', 'unit' => $m, 'purchase_price' => 180, 'selling_price' => 240, 'min_stock_level' => 40],
            ['sku' => 'ELC-001', 'name' => '2.5mm Copper Wire', 'category' => 'Electrical', 'brand' => 'Generic', 'unit' => $m, 'purchase_price' => 95, 'selling_price' => 130, 'min_stock_level' => 50],
            ['sku' => 'PNT-001', 'name' => 'Emulsion Paint 4L', 'category' => 'Paint & Finishing', 'brand' => 'Nippon', 'unit' => $litre, 'purchase_price' => 3200, 'selling_price' => 3850, 'min_stock_level' => 10],
            ['sku' => 'NAI-001', 'name' => 'Wire Nails 2 inch', 'category' => 'Hardware Fasteners', 'brand' => 'Generic', 'unit' => $kg, 'purchase_price' => 420, 'selling_price' => 520, 'min_stock_level' => 15],
            ['sku' => 'SCR-001', 'name' => 'Wood Screws Box', 'category' => 'Hardware Fasteners', 'brand' => 'Generic', 'unit' => $box, 'purchase_price' => 650, 'selling_price' => 850, 'min_stock_level' => 8],
            ['sku' => 'TLS-001', 'name' => 'Claw Hammer', 'category' => 'Tools', 'brand' => 'Bosch', 'unit' => $pcs, 'purchase_price' => 1450, 'selling_price' => 1890, 'min_stock_level' => 5],
        ];

        foreach ($products as $row) {
            Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => Category::query()->where('name', $row['category'])->value('id'),
                    'brand_id' => Brand::query()->where('name', $row['brand'])->value('id'),
                    'unit_id' => $row['unit']->id,
                    'purchase_price' => $row['purchase_price'],
                    'selling_price' => $row['selling_price'],
                    'min_stock_level' => $row['min_stock_level'],
                    'stock_quantity' => 0,
                    'is_active' => true,
                ]
            );
        }

        $admin = User::query()->where('email', 'admin@hardware.local')->first();
        $cement = Product::query()->where('sku', 'CEM-001')->first();
        $steel = Product::query()->where('sku', 'STE-001')->first();
        $bricks = Product::query()->where('sku', 'BRK-001')->first();
        $supplier = Supplier::query()->where('name', 'Lanka Building Materials')->first();

        if ($admin && $cement && $steel && $bricks && $supplier && $cement->stock_quantity == 0) {
            $purchaseService = app(PurchaseService::class);

            $purchase = $purchaseService->create([
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'discount' => 0,
                'tax' => 0,
                'notes' => 'Opening stock receipt',
            ], [
                ['product_id' => $cement->id, 'quantity' => 500, 'unit_cost' => 2150],
                ['product_id' => $steel->id, 'quantity' => 800, 'unit_cost' => 285],
                ['product_id' => $bricks->id, 'quantity' => 2000, 'unit_cost' => 28],
            ], $admin->id);

            $purchaseService->complete($purchase, $admin->id);

            $hammer = Product::query()->where('sku', 'TLS-001')->first();

            if ($hammer) {
                app(StockService::class)->record(
                    product: $hammer,
                    type: MovementType::OpeningBalance,
                    quantity: 3,
                    unitCost: 1450,
                    notes: 'Opening stock — below minimum for alert demo',
                    userId: $admin->id,
                );
            }
        }

        Customer::query()->updateOrCreate(
            ['phone' => '0771234567'],
            [
                'name' => 'Kumar',
                'email' => 'kumar@example.com',
                'address' => 'Colombo',
                'nic' => '198512345678',
                'credit_limit' => 50000,
                'is_active' => true,
            ],
        );
    }
}
