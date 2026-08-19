<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Jobs\EvaluateLowStockJob;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    /**
     * Record a stock movement and update the product's cached quantity.
     * Quantity is always passed as a positive amount; direction comes from movement type.
     */
    public function record(
        Product $product,
        MovementType $type,
        float|string $quantity,
        ?float $unitCost = null,
        ?Model $reference = null,
        ?string $notes = null,
        ?Carbon $movementDate = null,
        ?int $userId = null,
        bool $allowNegative = false,
    ): StockMovement {
        $absolute = round((float) $quantity, 3);

        if ($absolute <= 0) {
            throw new RuntimeException('Stock movement quantity must be greater than zero.');
        }

        $signedQuantity = $type->isInbound() ? $absolute : -$absolute;

        return DB::transaction(function () use (
            $product,
            $type,
            $signedQuantity,
            $unitCost,
            $reference,
            $notes,
            $movementDate,
            $userId,
            $allowNegative,
        ) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $newBalance = round((float) $locked->stock_quantity + $signedQuantity, 3);

            if (! $allowNegative && $newBalance < 0) {
                throw new RuntimeException("Insufficient stock for {$locked->name}. Available: {$locked->stock_quantity}.");
            }

            $locked->stock_quantity = $newBalance;
            $locked->save();

            $movement = StockMovement::query()->create([
                'product_id' => $locked->id,
                'movement_type' => $type,
                'quantity' => $signedQuantity,
                'balance_after' => $newBalance,
                'unit_cost' => $unitCost,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'user_id' => $userId ?? auth()->id(),
                'movement_date' => ($movementDate ?? now())->toDateString(),
            ]);

            DB::afterCommit(function () use ($locked) {
                EvaluateLowStockJob::dispatch($locked->id);
            });

            return $movement;
        });
    }
}
