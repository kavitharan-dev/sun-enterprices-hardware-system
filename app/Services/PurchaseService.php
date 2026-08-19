<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseService
{
    use LogsActivity;

    public function __construct(
        private readonly StockService $stockService,
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    public function create(array $data, array $items, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $totals = $this->calculateTotals($items, $data['discount'] ?? 0, $data['tax'] ?? 0);

            $purchase = Purchase::query()->create([
                'reference_no' => $this->documentNumbers->next('purchase_prefix', 'PO', Purchase::class),
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'status' => PurchaseStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->syncItems($purchase, $items);

            $this->logActivity(
                'created',
                'Purchase',
                "Created purchase {$purchase->reference_no}",
                $purchase,
            );

            return $purchase->fresh(['items', 'supplier']);
        });
    }

    public function update(Purchase $purchase, array $data, array $items): Purchase
    {
        if (! $purchase->isDraft()) {
            throw new RuntimeException('Only draft purchases can be updated.');
        }

        return DB::transaction(function () use ($purchase, $data, $items) {
            $totals = $this->calculateTotals($items, $data['discount'] ?? 0, $data['tax'] ?? 0);

            $purchase->update([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $purchase->items()->delete();
            $this->syncItems($purchase, $items);

            $this->logActivity(
                'updated',
                'Purchase',
                "Updated purchase {$purchase->reference_no}",
                $purchase,
            );

            return $purchase->fresh(['items', 'supplier']);
        });
    }

    public function complete(Purchase $purchase, ?int $userId = null): Purchase
    {
        if (! $purchase->isDraft()) {
            throw new RuntimeException('Only draft purchases can be completed.');
        }

        if ($purchase->items()->count() === 0) {
            throw new RuntimeException('Add at least one product before completing this purchase.');
        }

        return DB::transaction(function () use ($purchase, $userId) {
            $purchase->load('items.product');

            foreach ($purchase->items as $item) {
                $this->stockService->record(
                    product: $item->product,
                    type: MovementType::PurchaseIn,
                    quantity: (float) $item->quantity,
                    unitCost: (float) $item->unit_cost,
                    reference: $purchase,
                    notes: "Purchase {$purchase->reference_no}",
                    movementDate: $purchase->purchase_date,
                    userId: $userId ?? auth()->id(),
                );

                $item->product->update([
                    'purchase_price' => $item->unit_cost,
                ]);
            }

            $purchase->update([
                'status' => PurchaseStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->logActivity(
                'completed',
                'Purchase',
                "Received purchase {$purchase->reference_no} — stock increased",
                $purchase,
            );

            return $purchase->fresh(['items.product', 'supplier']);
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        if (! $purchase->isDraft()) {
            throw new RuntimeException('Completed purchases cannot be cancelled. Use a stock adjustment if needed.');
        }

        $purchase->update(['status' => PurchaseStatus::Cancelled]);

        $this->logActivity(
            'cancelled',
            'Purchase',
            "Cancelled purchase {$purchase->reference_no}",
            $purchase,
        );

        return $purchase;
    }

    /**
     * @param  array<int, array{product_id:int, quantity:float|string, unit_cost:float|string}>  $items
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    private function calculateTotals(array $items, float|string $discount = 0, float|string $tax = 0): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += round((float) $item['quantity'] * (float) $item['unit_cost'], 2);
        }

        $discount = round((float) $discount, 2);
        $tax = round((float) $tax, 2);
        $total = round($subtotal - $discount + $tax, 2);

        return compact('subtotal', 'discount', 'tax', 'total');
    }

    /**
     * @param  array<int, array{product_id:int, quantity:float|string, unit_cost:float|string}>  $items
     */
    private function syncItems(Purchase $purchase, array $items): void
    {
        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 3);
            $unitCost = round((float) $item['unit_cost'], 2);

            Product::query()->findOrFail($item['product_id']);

            $purchase->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'subtotal' => round($quantity * $unitCost, 2),
            ]);
        }
    }
}
