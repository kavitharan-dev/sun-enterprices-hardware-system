<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SunEnterpriseProductSeeder extends Seeder
{
    private const MIN_STOCK = 5;

    private const OPENING_STOCK = 20;

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private array $unitMap = [
        'bag' => ['Bag', 'bag'],
        'box' => ['Box', 'box'],
        'can' => ['Can', 'can'],
        'kg' => ['Kilogram', 'kg'],
        'length' => ['Length', 'length'],
        'litre' => ['Litre', 'L'],
        'm3' => ['Cubic metre', 'm³'],
        'm³' => ['Cubic metre', 'm³'],
        'pack' => ['Pack', 'pack'],
        'pair' => ['Pair', 'pair'],
        'piece' => ['Piece', 'pcs'],
        'roll' => ['Roll', 'roll'],
        'set' => ['Set', 'set'],
        'sheet' => ['Sheet', 'sheet'],
        'tin' => ['Tin', 'tin'],
        'tube' => ['Tube', 'tube'],
    ];

    public function run(): void
    {
        $path = database_path('data/sun_enterprise_products.csv');

        if (! File::exists($path)) {
            $this->command?->error("Product CSV not found: {$path}");

            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return;
        }

        $header = array_map(fn ($column) => trim((string) $column, "\xEF\xBB\xBF "), $header);
        $index = array_flip($header);
        $prices = SunEnterprisePriceSeeder::map();
        $sort = 1;
        $imported = 0;
        $stocked = 0;
        $adminId = User::query()->where('email', 'admin@hardware.local')->value('id')
            ?? User::query()->value('id');

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $categoryName = trim((string) ($row[$index['Category']] ?? ''));
            $brandName = trim((string) ($row[$index['Brand']] ?? 'Generic'));
            $productName = trim((string) ($row[$index['Product Name']] ?? ''));
            $unitRaw = trim((string) ($row[$index['Unit']] ?? 'piece'));
            $sku = strtoupper(trim((string) ($row[$index['SKU']] ?? '')));

            if ($sku === '' || $productName === '' || $categoryName === '') {
                continue;
            }

            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                [
                    'name' => $categoryName,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );

            $brand = Brand::query()->updateOrCreate(
                ['slug' => Str::slug($brandName) ?: 'generic'],
                [
                    'name' => $brandName !== '' ? $brandName : 'Generic',
                    'is_active' => true,
                ],
            );

            [$unitName, $unitSymbol] = $this->unitDefinition($unitRaw);
            $unit = Unit::query()->updateOrCreate(
                ['symbol' => $unitSymbol],
                [
                    'name' => $unitName,
                    'is_active' => true,
                ],
            );

            $defaults = $this->stockDefaults($unitSymbol);
            $price = $prices[$sku] ?? ['purchase' => 0.0, 'selling' => 0.0];

            $product = Product::query()->firstOrNew(['sku' => $sku]);
            $product->fill([
                'name' => $productName,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'unit_id' => $unit->id,
                'description' => $categoryName.' · '.$brandName,
                'min_stock_level' => $defaults['min'],
                'is_active' => true,
            ]);

            // Keep prices the shop has already edited; only fill the blanks.
            if ((float) $product->purchase_price <= 0) {
                $product->purchase_price = $price['purchase'];
            }

            if ((float) $product->selling_price <= 0) {
                $product->selling_price = $price['selling'];
            }

            $product->save();

            $imported++;

            $needsOpening = (float) $product->stock_quantity <= 0
                && ! $product->stockMovements()->exists();

            if ($needsOpening && $defaults['stock'] > 0) {
                app(StockService::class)->record(
                    product: $product,
                    type: MovementType::OpeningBalance,
                    quantity: $defaults['stock'],
                    unitCost: (float) $product->purchase_price,
                    notes: 'Placeholder opening stock from SUN ENTERPRICES catalog — update later',
                    userId: $adminId,
                );
                $stocked++;
            }
        }

        fclose($handle);

        $this->command?->info("Imported {$imported} products. Opening stock set on {$stocked} new items (min ".self::MIN_STOCK.', qty '.self::OPENING_STOCK.' unless unit-specific).');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function unitDefinition(string $raw): array
    {
        $key = $this->normalizeUnit($raw);

        return $this->unitMap[$key] ?? [Str::title($raw), $key !== '' ? $key : 'pcs'];
    }

    private function normalizeUnit(string $raw): string
    {
        $unit = trim(mb_strtolower($raw));

        if ($unit === 'm3' || str_contains($unit, '³') || $unit === 'm³' || str_contains($unit, 'cubic')) {
            return 'm³';
        }

        return $unit;
    }

    /**
     * Low minimum so items are not constantly flagged; opening qty is a placeholder above min.
     *
     * @return array{min: float, stock: float}
     */
    private function stockDefaults(string $symbol): array
    {
        return match ($symbol) {
            'm³' => ['min' => 1, 'stock' => 4],
            'kg' => ['min' => 5, 'stock' => 20],
            'bag' => ['min' => 5, 'stock' => 20],
            'pcs' => ['min' => 10, 'stock' => 40],
            'length' => ['min' => 5, 'stock' => 20],
            'L' => ['min' => 4, 'stock' => 16],
            'tube' => ['min' => 5, 'stock' => 20],
            'roll' => ['min' => 3, 'stock' => 12],
            'sheet' => ['min' => 5, 'stock' => 20],
            'box', 'pack', 'set', 'pair', 'tin', 'can' => ['min' => 3, 'stock' => 12],
            default => ['min' => self::MIN_STOCK, 'stock' => self::OPENING_STOCK],
        };
    }
}
