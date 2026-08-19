<?php

namespace App\Services;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\DailyAccountEntry;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashierTransactionService
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly PurchaseService $purchases,
        private readonly WorkerPayrollService $payroll,
        private readonly DailyAccountService $dailyAccounts,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Cashier records money once. Daily Accounts is posted and related pages
     * update from that same transaction.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(DailyAccountType $type, array $payload, User $cashier): DailyAccountEntry
    {
        if (! $cashier->canConfirmTill()) {
            throw new RuntimeException('Only the cashier records money in or out.');
        }

        return DB::transaction(function () use ($type, $payload, $cashier) {
            $entry = match ($type) {
                DailyAccountType::Sale => $this->recordSale($payload, $cashier),
                DailyAccountType::Purchase => $this->recordPurchase($payload, $cashier),
                DailyAccountType::WorkerAdvance => $this->recordAdvance($payload, $cashier),
                DailyAccountType::WorkerSettlement => $this->recordSettlement($payload, $cashier),
                DailyAccountType::OwnerPayment => $this->recordOwnerPayment($payload, $cashier),
                DailyAccountType::ProjectExpense => $this->recordProjectExpense($payload, $cashier),
                DailyAccountType::OtherIncome, DailyAccountType::OtherExpense => $this->recordOther($type, $payload, $cashier),
            };

            if (! $entry instanceof DailyAccountEntry) {
                throw new RuntimeException('The transaction was not recorded in Daily Accounts.');
            }

            return $entry;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordSale(array $payload, User $cashier): DailyAccountEntry
    {
        $sale = Sale::query()->find($payload['sale_id'] ?? null);
        if (! $sale) {
            throw new RuntimeException('Select the sale this payment belongs to.');
        }

        $payment = [
            'amount' => $payload['amount'] ?? 0,
            'method' => $payload['method'] ?? PaymentMethod::Cash->value,
            'payment_date' => $payload['occurred_on'] ?? now()->toDateString(),
            'reference' => $payload['reference_no'] ?? null,
            'notes' => $payload['description'] ?? null,
        ];

        if ($sale->isDraft()) {
            $sale = $this->sales->complete($sale, $payment, $cashier->id);
        } else {
            $this->sales->recordPayment($sale, [
                'amount' => $payment['amount'],
                'payment_method' => $payment['method'],
                'payment_date' => $payment['payment_date'],
                'reference' => $payment['reference'],
                'notes' => $payment['notes'],
            ], $cashier->id);
        }

        $record = $sale->fresh()->payments()->latest('id')->first();
        if (! $record?->daily_account_entry_id) {
            throw new RuntimeException('This sale did not take money (for example a credit sale).');
        }

        return DailyAccountEntry::query()->findOrFail($record->daily_account_entry_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordPurchase(array $payload, User $cashier): DailyAccountEntry
    {
        $purchase = Purchase::query()->find($payload['purchase_id'] ?? null);
        if (! $purchase) {
            throw new RuntimeException('Select the purchase to pay and receive into stock.');
        }

        $purchase = $this->purchases->complete($purchase, $cashier->id);

        if (! $purchase->daily_account_entry_id) {
            throw new RuntimeException('Purchase was not posted to Daily Accounts.');
        }

        return DailyAccountEntry::query()->findOrFail($purchase->daily_account_entry_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordAdvance(array $payload, User $cashier): DailyAccountEntry
    {
        $worker = Worker::query()->find($payload['worker_id'] ?? null);
        if (! $worker) {
            throw new RuntimeException('Select the worker.');
        }

        $payment = $this->payroll->recordAdvance($worker, [
            'amount' => $payload['amount'],
            'payment_date' => $payload['occurred_on'] ?? now()->toDateString(),
            'deduct_from_week' => (bool) ($payload['deduct_from_week'] ?? false),
            'project_id' => $payload['project_id'] ?? null,
            'notes' => $payload['description'] ?? null,
        ], $cashier->id);

        return DailyAccountEntry::query()->findOrFail($payment->daily_account_entry_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordSettlement(array $payload, User $cashier): DailyAccountEntry
    {
        $worker = Worker::query()->find($payload['worker_id'] ?? null);
        if (! $worker) {
            throw new RuntimeException('Select the worker.');
        }

        $week = $this->payroll->weekFor(
            $worker,
            $payload['occurred_on'] ?? now()->toDateString(),
            $cashier->id,
        );

        $this->payroll->settleWeek($week, [
            'amount' => $payload['amount'] ?? 0,
            'debt_deducted' => $payload['debt_deducted'] ?? 0,
            'project_id' => $payload['project_id'] ?? null,
            'notes' => $payload['description'] ?? null,
        ], $cashier->id);

        $payment = $week->fresh()->payments()->latest('id')->first();
        if (! $payment?->daily_account_entry_id) {
            throw new RuntimeException('No cash was paid for this settlement.');
        }

        return DailyAccountEntry::query()->findOrFail($payment->daily_account_entry_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordOwnerPayment(array $payload, User $cashier): DailyAccountEntry
    {
        $project = Project::query()->find($payload['project_id'] ?? null);
        if (! $project) {
            throw new RuntimeException('Select the project.');
        }

        $payment = $project->ownerPayments()->create([
            'amount' => $payload['amount'],
            'payment_date' => $payload['occurred_on'] ?? now()->toDateString(),
            'method' => $payload['method'] ?? PaymentMethod::Cash->value,
            'reference' => $payload['reference_no'] ?? null,
            'notes' => $payload['description'] ?? null,
            'received_by' => $cashier->id,
        ]);

        return $this->dailyAccounts->postOwnerPayment($payment->setRelation('project', $project));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordProjectExpense(array $payload, User $cashier): DailyAccountEntry
    {
        $project = Project::query()->find($payload['project_id'] ?? null);
        if (! $project) {
            throw new RuntimeException('Select the project.');
        }

        $expense = $project->expenses()->create([
            'category' => $payload['expense_category'] ?? ExpenseCategory::Other->value,
            'amount' => $payload['amount'],
            'expense_date' => $payload['occurred_on'] ?? now()->toDateString(),
            'description' => $payload['description'] ?? 'Site expense',
            'created_by' => $cashier->id,
        ]);

        $this->notifications->maybeNotifyBudgetAlert($project->fresh());

        $entry = $this->dailyAccounts->postProjectExpense($expense->setRelation('project', $project));
        if (! $entry) {
            throw new RuntimeException('This expense is not a cash movement.');
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordOther(DailyAccountType $type, array $payload, User $cashier): DailyAccountEntry
    {
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $category = DailyAccountCategory::tryFrom((string) ($payload['category'] ?? ''))
            ?? ($type === DailyAccountType::OtherIncome ? DailyAccountCategory::OtherIncome : DailyAccountCategory::Other);

        return $this->dailyAccounts->post([
            'occurred_on' => $payload['occurred_on'] ?? now()->toDateString(),
            'type' => $type,
            'category' => $category,
            'description' => $payload['description'] ?? $type->label(),
            'project_id' => $payload['project_id'] ?? null,
            'worker_id' => $payload['worker_id'] ?? null,
            'reference_no' => $payload['reference_no'] ?? null,
            'method' => $payload['method'] ?? null,
            'income' => $type->isIncome() ? $amount : 0,
            'expense' => $type->isIncome() ? 0 : $amount,
            'is_manual' => true,
            'recorded_by' => $cashier->id,
        ]);
    }
}
