<?php

namespace App\Services;

use App\Enums\CashierRequestStatus;
use App\Enums\CashierRequestType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\CashierRequest;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPayrollWeek;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashierRequestService
{
    use LogsActivity;

    public function __construct(
        private readonly SaleService $sales,
        private readonly PurchaseService $purchases,
        private readonly WorkerPayrollService $payroll,
        private readonly DailyAccountService $dailyAccounts,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Queue a money movement. If the actor is the cashier, confirm it at once
     * — they are already handling the cash.
     *
     * @param  array<string, mixed>  $payload
     */
    public function submit(CashierRequestType $type, array $payload, User $actor, ?object $subject = null): CashierRequest
    {
        if ($subject && $this->pendingFor($type, $subject)) {
            throw new RuntimeException('This item is already waiting for the cashier.');
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new RuntimeException('Enter the amount for the cashier to receive or pay.');
        }

        $request = CashierRequest::query()->create([
            'type' => $type,
            'status' => CashierRequestStatus::Pending,
            'direction' => $type->direction(),
            'amount' => $amount,
            'description' => $payload['description'] ?? $type->label(),
            'project_id' => $payload['project_id'] ?? null,
            'worker_id' => $payload['worker_id'] ?? null,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'payload' => $payload,
            'method' => $payload['method'] ?? null,
            'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'requested_by' => $actor->id,
        ]);

        if ($actor->canHandleTill()) {
            return $this->confirm($request, [
                'method' => $payload['method'] ?? PaymentMethod::Cash->value,
                'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ], $actor);
        }

        $this->logActivity(
            'queued',
            'CashierRequest',
            "Sent {$type->label()} of Rs. ".number_format($amount, 2).' to the cashier',
            $request,
        );

        return $request;
    }

    /**
     * @param  array<string, mixed>  $till
     */
    public function confirm(CashierRequest $request, array $till, User $cashier): CashierRequest
    {
        if (! $cashier->canConfirmTill()) {
            throw new RuntimeException('Only the cashier can confirm money in or out.');
        }

        if (! $request->isPending()) {
            throw new RuntimeException('This request has already been handled.');
        }

        return DB::transaction(function () use ($request, $till, $cashier) {
            $method = PaymentMethod::tryFrom((string) ($till['method'] ?? $request->method?->value ?? PaymentMethod::Cash->value))
                ?? PaymentMethod::Cash;

            if ($method === PaymentMethod::Credit) {
                throw new RuntimeException('The cashier records actual money. Use cash, card, or bank transfer.');
            }

            $request->update([
                'method' => $method,
                'payment_date' => $till['payment_date'] ?? $request->payment_date?->toDateString() ?? now()->toDateString(),
                'reference' => $till['reference'] ?? $request->reference,
                'notes' => $till['notes'] ?? $request->notes,
            ]);

            $this->execute($request->fresh(), $cashier);

            $request->update([
                'status' => CashierRequestStatus::Confirmed,
                'confirmed_by' => $cashier->id,
                'confirmed_at' => now(),
            ]);

            $this->logActivity(
                'confirmed',
                'CashierRequest',
                "Cashier confirmed {$request->type->label()} of Rs. ".number_format((float) $request->amount, 2),
                $request,
            );

            return $request->fresh();
        });
    }

    public function reject(CashierRequest $request, User $cashier, ?string $reason = null): CashierRequest
    {
        if (! $cashier->canConfirmTill()) {
            throw new RuntimeException('Only the cashier can reject a payment request.');
        }

        if (! $request->isPending()) {
            throw new RuntimeException('This request has already been handled.');
        }

        $request->update([
            'status' => CashierRequestStatus::Rejected,
            'confirmed_by' => $cashier->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->logActivity('rejected', 'CashierRequest', "Cashier rejected {$request->type->label()}", $request);

        return $request->fresh();
    }

    public function pendingFor(CashierRequestType $type, object $subject): ?CashierRequest
    {
        return CashierRequest::query()
            ->pending()
            ->where('type', $type)
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->first();
    }

    private function execute(CashierRequest $request, User $cashier): void
    {
        $payload = $request->payload ?? [];
        $payload['method'] = $request->method?->value ?? PaymentMethod::Cash->value;
        $payload['payment_date'] = $request->payment_date?->toDateString() ?? now()->toDateString();
        $payload['reference'] = $request->reference;
        $payload['notes'] = $request->notes;
        $payload['amount'] = (float) $request->amount;

        match ($request->type) {
            CashierRequestType::SalePayment => $this->executeSale($request, $payload, $cashier),
            CashierRequestType::PurchasePayment => $this->executePurchase($request, $cashier),
            CashierRequestType::WorkerAdvance => $this->executeAdvance($request, $payload, $cashier),
            CashierRequestType::WorkerSettlement => $this->executeSettlement($request, $payload, $cashier),
            CashierRequestType::OwnerPayment => $this->executeOwnerPayment($request, $payload, $cashier),
            CashierRequestType::ProjectExpense => $this->executeProjectExpense($request, $payload, $cashier),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeSale(CashierRequest $request, array $payload, User $cashier): void
    {
        $sale = $request->subject;
        if (! $sale instanceof Sale) {
            throw new RuntimeException('Sale is missing.');
        }

        if ($sale->isDraft()) {
            $this->sales->complete($sale, [
                'amount' => $payload['amount'],
                'method' => $payload['method'],
                'payment_date' => $payload['payment_date'],
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ], $cashier->id);

            return;
        }

        $this->sales->recordPayment($sale, [
            'amount' => $payload['amount'],
            'payment_method' => $payload['method'],
            'payment_date' => $payload['payment_date'],
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ], $cashier->id);
    }

    private function executePurchase(CashierRequest $request, User $cashier): void
    {
        $purchase = $request->subject;
        if (! $purchase instanceof Purchase) {
            throw new RuntimeException('Purchase is missing.');
        }

        $this->purchases->complete($purchase, $cashier->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeAdvance(CashierRequest $request, array $payload, User $cashier): void
    {
        $worker = $request->worker ?? Worker::query()->find($payload['worker_id'] ?? $request->worker_id);
        if (! $worker) {
            throw new RuntimeException('Worker is missing.');
        }

        $this->payroll->recordAdvance($worker, $payload, $cashier->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeSettlement(CashierRequest $request, array $payload, User $cashier): void
    {
        $week = $request->subject;
        if (! $week instanceof WorkerPayrollWeek) {
            throw new RuntimeException('Pay week is missing.');
        }

        $this->payroll->settleWeek($week, $payload, $cashier->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeOwnerPayment(CashierRequest $request, array $payload, User $cashier): void
    {
        $project = $request->project ?? Project::query()->find($request->project_id);
        if (! $project) {
            throw new RuntimeException('Project is missing.');
        }

        $payment = $project->ownerPayments()->create([
            'amount' => $payload['amount'],
            'payment_date' => $payload['payment_date'],
            'method' => $payload['method'],
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'received_by' => $cashier->id,
        ]);

        $this->dailyAccounts->postOwnerPayment($payment->setRelation('project', $project));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeProjectExpense(CashierRequest $request, array $payload, User $cashier): void
    {
        $project = $request->project ?? Project::query()->find($request->project_id);
        if (! $project) {
            throw new RuntimeException('Project is missing.');
        }

        $expense = $project->expenses()->create([
            'category' => $payload['category'] ?? ExpenseCategory::Other->value,
            'amount' => $payload['amount'],
            'expense_date' => $payload['expense_date'] ?? $payload['payment_date'],
            'description' => $payload['expense_description'] ?? $request->description,
            'created_by' => $cashier->id,
        ]);

        $this->notifications->maybeNotifyBudgetAlert($project->fresh());
        $this->dailyAccounts->postProjectExpense($expense->setRelation('project', $project));
    }
}
