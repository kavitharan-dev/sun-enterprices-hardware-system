<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SunEnterprisePriceSeeder extends Seeder
{
    public function run(): void
    {
        $prices = self::map();

        if ($prices === []) {
            $this->command?->error('Price CSV not found: '.self::path());

            return;
        }

        $updated = 0;
        $missing = [];

        foreach ($prices as $sku => $price) {
            $product = Product::query()->where('sku', $sku)->first();

            if (! $product) {
                $missing[] = $sku;

                continue;
            }

            $product->forceFill([
                'purchase_price' => $price['purchase'],
                'selling_price' => $price['selling'],
            ])->save();

            $updated++;
        }

        $this->command?->info("Priced {$updated} products from the Sri Lanka price list.");

        if ($missing !== []) {
            $this->command?->warn('No product row for: '.implode(', ', $missing));
        }
    }

    /**
     * Indicative Sri Lankan trade/retail prices keyed by SKU.
     *
     * @return array<string, array{purchase: float, selling: float}>
     */
    public static function map(): array
    {
        $path = self::path();

        if (! File::exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $prices = [];

        while (($row = fgetcsv($handle)) !== false) {
            $sku = strtoupper(trim((string) ($row[0] ?? '')));
            $purchase = (float) ($row[2] ?? 0);
            $selling = (float) ($row[3] ?? 0);

            if ($sku === '' || $selling <= 0) {
                continue;
            }

            $prices[$sku] = ['purchase' => $purchase, 'selling' => $selling];
        }

        fclose($handle);

        return $prices;
    }

    private static function path(): string
    {
        return database_path('data/sun_enterprise_prices.csv');
    }
}
