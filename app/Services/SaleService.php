<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleService
{
    use LogsActivity;

    public function __construct(
        private readonly StockService $stockService,
        private readonly DocumentNumberService $documentNumbers,
        private readonly NotificationService $notifications,
        private readonly DailyAccountService $dailyAccounts,
    ) {}

    public function create(array $data, array $items, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $totals = $this->calculateTotals($items, $data['discount'] ?? 0, $data['tax'] ?? 0);
            $previousIncluded = $this->resolvePreviousBalanceIncluded($data);
            $total = round($totals['total'] + $previousIncluded, 2);

            $sale = Sale::query()->create([
                'customer_id' => ($data['customer_id'] ?? null) ?: null,
                'walk_in_name' => ($data['customer_id'] ?? null) ? null : ($data['walk_in_name'] ?? null),
                'sale_date' => $data['sale_date'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'tax' => $totals['tax'],
                'total' => $total,
                'previous_balance_included' => $previousIncluded,
                'paid_amount' => 0,
                'balance' => $total,
                'payment_status' => PaymentStatus::Unpaid,
                'status' => SaleStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->syncItems($sale, $items);

            $this->logActivity('created', 'Sale', "Created draft sale #{$sale->id}", $sale);

            return $sale->fresh(['items', 'customer']);
        });
    }

    public function update(Sale $sale, array $data, array $items): Sale
    {
        if (! $sale->isDraft()) {
            throw new RuntimeException('Only draft sales can be updated.');
        }

        return DB::transaction(function () use ($sale, $data, $items) {
            $totals = $this->calculateTotals($items, $data['discount'] ?? 0, $data['tax'] ?? 0);
            $previousIncluded = $this->resolvePreviousBalanceIncluded($data, $sale);
            $total = round($totals['total'] + $previousIncluded, 2);

            $sale->update([
                'customer_id' => ($data['customer_id'] ?? null) ?: null,
                'walk_in_name' => ($data['customer_id'] ?? null) ? null : ($data['walk_in_name'] ?? null),
                'sale_date' => $data['sale_date'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'tax' => $totals['tax'],
                'total' => $total,
                'previous_balance_included' => $previousIncluded,
                'balance' => $total,
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->items()->delete();
            $this->syncItems($sale, $items);

            $this->logActivity('updated', 'Sale', "Updated draft sale #{$sale->id}", $sale);

            return $sale->fresh(['items', 'customer']);
        });
    }

    public function complete(Sale $sale, array $payment = [], ?int $userId = null): Sale
    {
        if (! $sale->isDraft()) {
            throw new RuntimeException('Only draft sales can be completed.');
        }

        if ($sale->items()->count() === 0) {
            throw new RuntimeException('Add at least one product before completing this sale.');
        }

        return DB::transaction(function () use ($sale, $payment, $userId) {
            $sale->load(['items.product', 'customer']);

            $method = PaymentMethod::tryFrom((string) ($payment['method'] ?? PaymentMethod::Cash->value))
                ?? PaymentMethod::Cash;
            $tendered = round((float) ($payment['amount'] ?? 0), 2);

            if ($method->requiresPaidAmount() && $tendered <= 0) {
                throw new RuntimeException('Enter the amount the customer paid before completing this sale. Use Credit if they will pay later.');
            }

            if ($method === PaymentMethod::Credit) {
                $tendered = 0;
            }

            foreach ($sale->items as $item) {
                if ((float) $item->product->stock_quantity < (float) $item->quantity) {
                    throw new RuntimeException("Insufficient stock for {$item->product->name}. Available: {$item->product->stock_quantity}.");
                }
            }

            foreach ($sale->items as $item) {
                $this->stockService->record(
                    product: $item->product,
                    type: MovementType::SaleOut,
                    quantity: (float) $item->quantity,
                    unitCost: (float) $item->product->purchase_price,
                    reference: $sale,
                    notes: 'Sale stock out',
                    movementDate: $sale->sale_date,
                    userId: $userId ?? auth()->id(),
                );
            }

            $invoiceNo = $this->documentNumbers->next('invoice_prefix', 'INV', Sale::class, 'invoice_no');
            $total = (float) $sale->total;

            $applied = $method === PaymentMethod::Credit
                ? 0.0
                : min($tendered, $total);
            $change = $method === PaymentMethod::Credit
                ? 0.0
                : round(max($tendered - $total, 0), 2);

            $sale->update([
                'invoice_no' => $invoiceNo,
                'status' => SaleStatus::Completed,
                'completed_at' => now(),
                'tendered_amount' => $tendered,
                'change_amount' => $change,
            ]);

            if ($applied > 0) {
                $paymentData = [
                    'amount' => $applied,
                    'payment_method' => $method->value,
                    'payment_date' => $payment['payment_date'] ?? $sale->sale_date,
                    'reference' => $payment['reference'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                ];

                if ($sale->customer_id && (float) $sale->previous_balance_included > 0) {
                    $allocatedToOlder = $this->allocateCustomerPayment($sale, $paymentData, $applied, $userId ?? auth()->id(), notify: false);
                    $this->refreshPaymentState($sale, $allocatedToOlder);
                } else {
                    $this->applyPayment($sale, $paymentData, $userId ?? auth()->id(), notify: false);
                }
            } else {
                $this->refreshPaymentState($sale);
            }

            $sale->refresh();
            $sale->customer?->refreshOutstandingBalance();

            $this->logActivity(
                'completed',
                'Sale',
                "Completed sale {$invoiceNo} — stock reduced",
                $sale,
            );

            $this->notifications->invoiceNotification(
                $sale,
                "Invoice {$invoiceNo} for {$sale->customerName()} — Rs. ".number_format((float) $sale->total, 2),
                $sale->customer?->phone,
                $sale->customer?->email,
            );

            return $sale->fresh(['items.product.unit', 'customer', 'payments']);
        });
    }

    public function recordPayment(Sale $sale, array $payment, int $userId): Payment
    {
        if (! $sale->isCompleted()) {
            throw new RuntimeException('Payments can only be recorded on completed sales.');
        }

        if ((float) $sale->balance <= 0) {
            throw new RuntimeException('This invoice is already fully paid.');
        }

        return DB::transaction(function () use ($sale, $payment, $userId) {
            $record = $this->applyPayment($sale, $payment, $userId, notify: true);
            $sale->customer?->refreshOutstandingBalance();

            $this->logActivity(
                'payment',
                'Sale',
                "Recorded payment of Rs. {$record->amount} on {$sale->invoice_no}",
                $sale,
            );

            return $record;
        });
    }

    public function cancel(Sale $sale): Sale
    {
        if (! $sale->isDraft()) {
            throw new RuntimeException('Completed sales cannot be cancelled from here. Use a return if stock must be restored.');
        }

        $sale->update(['status' => SaleStatus::Cancelled]);
        $this->logActivity('cancelled', 'Sale', "Cancelled draft sale #{$sale->id}", $sale);

        return $sale;
    }

    /**
     * @param  array<int, array{product_id:int, quantity:float|string, unit_price:float|string, discount?:float|string}>  $items
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    private function calculateTotals(array $items, float|string $discount = 0, float|string $tax = 0): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount'] ?? 0);
            $subtotal += round(max($line, 0), 2);
        }

        $discount = round((float) $discount, 2);
        $tax = round((float) $tax, 2);
        $total = round(max($subtotal - $discount + $tax, 0), 2);

        return compact('subtotal', 'discount', 'tax', 'total');
    }

    private function resolvePreviousBalanceIncluded(array $data, ?Sale $exclude = null): float
    {
        if (empty($data['customer_id']) || ! $this->shouldIncludePreviousBalance($data)) {
            return 0.0;
        }

        $customer = Customer::query()->find($data['customer_id']);

        return $customer?->priorOutstanding($exclude) ?? 0.0;
    }

    private function shouldIncludePreviousBalance(array $data): bool
    {
        return filter_var($data['include_previous_balance'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function allocateCustomerPayment(Sale $sale, array $payment, float $amount, int $userId, bool $notify): float
    {
        $remaining = round($amount, 2);
        $allocatedToOlder = 0.0;
        $customer = $sale->customer;

        if (! $customer) {
            $this->applyPayment($sale, array_merge($payment, ['amount' => $remaining]), $userId, $notify);

            return 0.0;
        }

        $olderSales = $customer->sales()
            ->where('status', SaleStatus::Completed)
            ->where('id', '!=', $sale->id)
            ->where('balance', '>', 0)
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        foreach ($olderSales as $olderSale) {
            if ($remaining <= 0) {
                break;
            }

            $portion = min((float) $olderSale->balance, $remaining);

            $this->applyPayment($olderSale, [
                'amount' => $portion,
                'payment_method' => $payment['payment_method'],
                'payment_date' => $payment['payment_date'] ?? now()->toDateString(),
                'reference' => $payment['reference'] ?? null,
                'notes' => trim('Collected via '.$sale->invoice_no.($payment['notes'] ? ' — '.$payment['notes'] : '')),
            ], $userId, notify: false);

            $allocatedToOlder = round($allocatedToOlder + $portion, 2);
            $remaining = round($remaining - $portion, 2);
        }

        if ($remaining > 0) {
            $this->applyPayment($sale, array_merge($payment, ['amount' => $remaining]), $userId, $notify);
        }

        return $allocatedToOlder;
    }

    private function syncItems(Sale $sale, array $items): void
    {
        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 3);
            $unitPrice = round((float) $item['unit_price'], 2);
            $lineDiscount = round((float) ($item['discount'] ?? 0), 2);

            Product::query()->findOrFail($item['product_id']);

            $sale->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'subtotal' => round(max(($quantity * $unitPrice) - $lineDiscount, 0), 2),
            ]);
        }
    }

    private function applyPayment(Sale $sale, array $payment, int $userId, bool $notify): Payment
    {
        $amount = round((float) $payment['amount'], 2);

        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        if ($amount > (float) $sale->balance) {
            throw new RuntimeException('Payment cannot exceed the remaining balance.');
        }

        $record = $sale->payments()->create([
            'amount' => $amount,
            'payment_method' => $payment['payment_method'],
            'payment_date' => $payment['payment_date'] ?? now()->toDateString(),
            'reference' => $payment['reference'] ?? null,
            'notes' => $payment['notes'] ?? null,
            'received_by' => $userId,
        ]);

        $this->refreshPaymentState($sale);

        $sale->loadMissing('customer');
        $this->dailyAccounts->postSalePayment($record->setRelation('payable', $sale));

        if ($notify) {
            $this->notifications->paymentReceived(
                $record,
                'Payment of Rs. '.number_format($amount, 2)." received for invoice {$sale->invoice_no}.",
                $sale->customer?->phone,
            );
        }

        return $record;
    }

    private function refreshPaymentState(Sale $sale, float $priorBalanceSettled = 0.0): void
    {
        $paidOnSale = round((float) $sale->payments()->sum('amount'), 2);
        $priorSettled = round(min($priorBalanceSettled, (float) $sale->previous_balance_included), 2);
        $paid = round($paidOnSale + $priorSettled, 2);
        $total = (float) $sale->total;
        $balance = round(max($total - $paid, 0), 2);

        $status = match (true) {
            $paid <= 0 => PaymentStatus::Unpaid,
            $balance > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Paid,
        };

        $sale->update([
            'paid_amount' => $paid,
            'balance' => $balance,
            'payment_status' => $status,
        ]);
    }
}
