<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class EvaluateLowStockJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public int $productId) {}

    public function handle(NotificationService $notifications): void
    {
        $product = Product::query()->with('unit')->find($this->productId);

        if ($product && $product->isLowStock()) {
            $notifications->criticalLowStock($product);
        }
    }
}
