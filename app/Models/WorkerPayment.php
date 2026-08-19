<?php

namespace App\Models;

use App\Enums\WorkerPaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerPayment extends Model
{
    protected $fillable = [
        'worker_id',
        'worker_payroll_week_id',
        'project_id',
        'type',
        'payment_date',
        'amount',
        'deduct_from_week',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkerPaymentType::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'deduct_from_week' => 'boolean',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(WorkerPayrollWeek::class, 'worker_payroll_week_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isAdvance(): bool
    {
        return $this->type === WorkerPaymentType::Advance;
    }

    public function isSettlement(): bool
    {
        return $this->type === WorkerPaymentType::Settlement;
    }

    /**
     * An advance the worker chose not to have deducted becomes debt.
     */
    public function createsDebt(): bool
    {
        return $this->isAdvance() && ! $this->deduct_from_week;
    }

    public function effect(): string
    {
        if ($this->isSettlement()) {
            return 'Saturday payout';
        }

        return $this->deduct_from_week
            ? 'Deducted from this week'
            : 'Added to worker debt';
    }
}
