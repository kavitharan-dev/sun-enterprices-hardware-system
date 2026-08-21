<?php

namespace App\Services;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\CashierRequest;
use App\Models\DailyAccountDay;
use App\Models\DailyAccountEntry;
use App\Models\Payment;
use App\Models\ProjectExpense;
use App\Models\ProjectOwnerPayment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Models\WorkerPayment;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyAccountService
{
    use LogsActivity;

    private ?CashierRequest $cashierRequest = null;

    /**
     * The cashier request currently being confirmed. Posts attach to it so
     * Daily Accounts and the related page share one transaction id.
     */
    public function usingCashierRequest(?CashierRequest $request): void
    {
        $this->cashierRequest = $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(array $data, ?Model $source = null): DailyAccountEntry
    {
        return DB::transaction(function () use ($data, $source) {
            if ($source) {
                $existing = DailyAccountEntry::query()
                    ->where('source_type', $source::class)
                    ->where('source_id', $source->getKey())
                    ->first();

                if ($existing) {
                    $this->attach($existing, $source);

                    return $existing;
                }
            }

            if ($this->cashierRequest) {
                $fromRequest = DailyAccountEntry::query()
                    ->where('cashier_request_id', $this->cashierRequest->id)
                    ->first();

                if ($fromRequest) {
                    $this->attach($fromRequest, $source);

                    return $fromRequest;
                }
            }

            $this->assertDayOpen((string) $data['occurred_on']);

            $income = round((float) ($data['income'] ?? 0), 2);
            $expense = round((float) ($data['expense'] ?? 0), 2);

            $entry = DailyAccountEntry::query()->create([
                'occurred_on' => $data['occurred_on'],
                'type' => $data['type'],
                'category' => $data['category'],
                'description' => $data['description'],
                'project_id' => $data['project_id'] ?? null,
                'worker_id' => $data['worker_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'cashier_request_id' => $this->cashierRequest?->id,
                'method' => $data['method'] ?? null,
                'income' => $income,
                'expense' => $expense,
                'is_manual' => (bool) ($data['is_manual'] ?? false),
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $entry->update([
                'transaction_no' => sprintf('TXN-%06d', $entry->id),
            ]);

            $this->attach($entry->fresh(), $source);

            return $entry->fresh();
        });
    }

    private function attach(DailyAccountEntry $entry, ?Model $source): void
    {
        if ($source && method_exists($source, 'financialTransaction')) {
            if ((int) $source->getAttribute('daily_account_entry_id') !== (int) $entry->id) {
                $source->forceFill(['daily_account_entry_id' => $entry->id])->saveQuietly();
            }
        }

        if ($this->cashierRequest && (int) $entry->cashier_request_id !== (int) $this->cashierRequest->id) {
            $entry->forceFill(['cashier_request_id' => $this->cashierRequest->id])->saveQuietly();
        }
    }

    public function removeFor(Model $source): void
    {
        $entry = DailyAccountEntry::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->first();

        if (! $entry) {
            return;
        }

        $this->assertDayOpen($entry->occurred_on->toDateString());
        $entry->delete();
    }

    public function postSalePayment(Payment $payment): DailyAccountEntry
    {
        $sale = $payment->payable;
        $invoice = $sale instanceof Sale ? ($sale->invoice_no ?: '#'.$sale->id) : 'sale';
        $customer = $sale instanceof Sale ? $sale->customerName() : 'Customer';

        return $this->post([
            'occurred_on' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
            'type' => DailyAccountType::Sale,
            'category' => DailyAccountCategory::HardwareSale,
            'description' => "Sale {$invoice} — {$customer}",
            'reference_no' => $invoice,
            'method' => $payment->payment_method,
            'income' => $payment->amount,
            'recorded_by' => $payment->received_by,
        ], $payment);
    }

    public function postPurchase(Purchase $purchase, ?int $userId = null): DailyAccountEntry
    {
        $supplier = $purchase->supplier?->name ?? 'Supplier';

        return $this->post([
            'occurred_on' => $purchase->purchase_date->toDateString(),
            'type' => DailyAccountType::Purchase,
            'category' => DailyAccountCategory::StockPurchase,
            'description' => "Purchase {$purchase->reference_no} — {$supplier}",
            'reference_no' => $purchase->reference_no,
            'expense' => $purchase->total,
            'recorded_by' => $userId ?? $purchase->created_by,
        ], $purchase);
    }

    public function postWorkerPayment(WorkerPayment $payment): DailyAccountEntry
    {
        $isAdvance = $payment->isAdvance();
        $worker = $payment->worker?->name ?? 'Worker';

        return $this->post([
            'occurred_on' => $payment->payment_date->toDateString(),
            'type' => $isAdvance ? DailyAccountType::WorkerAdvance : DailyAccountType::WorkerSettlement,
            'category' => $isAdvance ? DailyAccountCategory::WorkerAdvance : DailyAccountCategory::WorkerSalary,
            'description' => ($isAdvance ? 'Advance to ' : 'Saturday wages — ').$worker
                .($payment->notes ? " ({$payment->notes})" : ''),
            'project_id' => $payment->project_id,
            'worker_id' => $payment->worker_id,
            'reference_no' => $payment->week?->label(),
            'method' => PaymentMethod::Cash,
            'expense' => $payment->amount,
            'recorded_by' => $payment->recorded_by,
        ], $payment);
    }

    public function postOwnerPayment(ProjectOwnerPayment $payment): DailyAccountEntry
    {
        $project = $payment->project?->name ?? 'Project';

        return $this->post([
            'occurred_on' => $payment->payment_date->toDateString(),
            'type' => DailyAccountType::OwnerPayment,
            'category' => DailyAccountCategory::OwnerPayment,
            'description' => "Site owner payment — {$project}",
            'project_id' => $payment->project_id,
            'reference_no' => $payment->reference,
            'method' => $payment->method,
            'income' => $payment->amount,
            'recorded_by' => $payment->received_by,
        ], $payment);
    }

    public function postProjectExpense(ProjectExpense $expense): ?DailyAccountEntry
    {
        if ($expense->isAutomatic()) {
            return null;
        }

        $project = $expense->project?->name ?? 'Project';
        $category = $expense->category instanceof ExpenseCategory
            ? $expense->category
            : ExpenseCategory::Other;

        return $this->post([
            'occurred_on' => $expense->expense_date->toDateString(),
            'type' => DailyAccountType::ProjectExpense,
            'category' => DailyAccountCategory::fromExpense($category),
            'description' => "{$expense->category->label()} — {$project}: {$expense->description}",
            'project_id' => $expense->project_id,
            'expense' => $expense->amount,
            'recorded_by' => $expense->created_by,
        ], $expense);
    }

    public function dayFor(string $date, ?int $userId = null): DailyAccountDay
    {
        $existing = DailyAccountDay::query()->whereDate('business_date', $date)->first();

        if ($existing) {
            return $existing;
        }

        return DailyAccountDay::query()->create([
            'business_date' => $date,
            'opening_balance' => $this->previousClosing($date),
            'updated_by' => $userId,
        ]);
    }

    public function setOpening(string $date, float $amount, ?int $userId = null, ?string $notes = null): DailyAccountDay
    {
        $this->assertDayOpen($date);

        $day = $this->dayFor($date, $userId);
        $day->update([
            'opening_balance' => round($amount, 2),
            'notes' => $notes ?? $day->notes,
            'updated_by' => $userId,
        ]);

        return $day->fresh();
    }

    /**
     * Lock the till for the day and snapshot the system closing balance.
     * Optional counted cash is kept for the paper till check / variance.
     */
    public function closeDay(
        string $date,
        User $user,
        ?float $countedCash = null,
        ?string $notes = null,
    ): DailyAccountDay {
        return DB::transaction(function () use ($date, $user, $countedCash, $notes) {
            $day = $this->dayFor($date, $user->id);

            if ($day->isClosed()) {
                throw new RuntimeException('This day is already closed.');
            }

            $totals = $this->totalsFor($date);

            $day->update([
                'is_closed' => true,
                'closing_balance' => $totals['closing'],
                'counted_cash' => $countedCash === null ? null : round($countedCash, 2),
                'close_notes' => $notes,
                'closed_at' => now(),
                'closed_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->logActivity(
                'closed',
                'DailyAccounts',
                "Closed till for {$date}. System closing Rs. ".number_format($totals['closing'], 2),
                $day,
                [
                    'closing_balance' => $totals['closing'],
                    'counted_cash' => $countedCash,
                ],
                $user->id,
            );

            return $day->fresh(['closer']);
        });
    }

    public function reopenDay(string $date, User $user): DailyAccountDay
    {
        if (! $user->hasRole('admin')) {
            throw new RuntimeException('Only an admin can reopen a closed day.');
        }

        return DB::transaction(function () use ($date, $user) {
            $day = $this->dayFor($date, $user->id);

            if (! $day->isClosed()) {
                throw new RuntimeException('This day is not closed.');
            }

            $day->update([
                'is_closed' => false,
                'closing_balance' => null,
                'counted_cash' => null,
                'close_notes' => null,
                'closed_at' => null,
                'closed_by' => null,
                'updated_by' => $user->id,
            ]);

            $this->logActivity(
                'reopened',
                'DailyAccounts',
                "Reopened till for {$date}",
                $day,
                userId: $user->id,
            );

            return $day->fresh();
        });
    }

    public function assertDayOpen(string $date): void
    {
        $day = DailyAccountDay::query()->whereDate('business_date', $date)->first();

        if ($day?->isClosed()) {
            throw new RuntimeException(
                'Daily accounts for '.$day->business_date->format('d/m/Y').' are closed. Reopen the day (admin) or use another date.'
            );
        }
    }

    /**
     * Full day pack for on-screen print and PDF filing.
     *
     * @return array{
     *     day: DailyAccountDay,
     *     totals: array{opening: float, income: float, expense: float, closing: float},
     *     rows: Collection<int, array{entry: DailyAccountEntry, balance: float}>,
     *     variance: float|null
     * }
     */
    public function dayReport(string $date): array
    {
        $day = $this->dayFor($date);
        $totals = $this->totalsFor($date);
        $entries = $this->entries(['from' => $date, 'to' => $date]);

        $running = $totals['opening'];
        $rows = $entries->map(function (DailyAccountEntry $entry) use (&$running) {
            $running = round($running + $entry->net(), 2);

            return ['entry' => $entry, 'balance' => $running];
        });

        $variance = null;
        if ($day->isClosed() && $day->counted_cash !== null) {
            $systemClosing = $day->closing_balance !== null
                ? (float) $day->closing_balance
                : $totals['closing'];
            $variance = round((float) $day->counted_cash - $systemClosing, 2);
        }

        return [
            'day' => $day->loadMissing('closer'),
            'totals' => $totals,
            'rows' => $rows,
            'variance' => $variance,
        ];
    }

    /**
     * Opening + income − expenses for one calendar day.
     *
     * @return array{opening: float, income: float, expense: float, closing: float}
     */
    public function totalsFor(string $date): array
    {
        $opening = (float) $this->dayFor($date)->opening_balance;
        $income = (float) DailyAccountEntry::query()->whereDate('occurred_on', $date)->sum('income');
        $expense = (float) DailyAccountEntry::query()->whereDate('occurred_on', $date)->sum('expense');

        return [
            'opening' => round($opening, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'closing' => round($opening + $income - $expense, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, DailyAccountEntry>
     */
    public function entries(array $filters): Collection
    {
        $query = DailyAccountEntry::query()
            ->with(['project', 'worker'])
            ->orderBy('occurred_on')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($from) {
            $query->whereDate('occurred_on', '>=', $from);
        }

        if ($to) {
            $query->whereDate('occurred_on', '<=', $to);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['worker_id'])) {
            $query->where('worker_id', $filters['worker_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (($filters['direction'] ?? '') === 'income') {
            $query->where('income', '>', 0);
        }

        if (($filters['direction'] ?? '') === 'expense') {
            $query->where('expense', '>', 0);
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }
    }

    private function previousClosing(string $date): float
    {
        $previousDay = DailyAccountDay::query()
            ->whereDate('business_date', '<', $date)
            ->orderByDesc('business_date')
            ->first();

        if ($previousDay) {
            $dayDate = $previousDay->business_date->toDateString();
            $income = (float) DailyAccountEntry::query()->whereDate('occurred_on', $dayDate)->sum('income');
            $expense = (float) DailyAccountEntry::query()->whereDate('occurred_on', $dayDate)->sum('expense');

            return round((float) $previousDay->opening_balance + $income - $expense, 2);
        }

        $income = (float) DailyAccountEntry::query()->whereDate('occurred_on', '<', $date)->sum('income');
        $expense = (float) DailyAccountEntry::query()->whereDate('occurred_on', '<', $date)->sum('expense');

        return round($income - $expense, 2);
    }
}
