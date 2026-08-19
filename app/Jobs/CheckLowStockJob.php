<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckLowStockJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notifications): void
    {
        Product::query()
            ->with('unit')
            ->lowStock()
            ->where('is_active', true)
            ->get()
            ->each(function (Product $product) use ($notifications) {
                $outOfStock = (float) $product->stock_quantity <= 0;
                $notifications->criticalLowStock($product, $outOfStock);
            });
    }
}
