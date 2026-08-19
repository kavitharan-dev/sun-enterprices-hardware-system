<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkerPayrollWeek extends Model
{
    protected $fillable = [
        'worker_id',
        'week_start',
        'week_end',
        'weekly_salary',
        'debt_deducted',
        'notes',
        'settled_at',
        'settled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'weekly_salary' => 'decimal:2',
            'debt_deducted' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WorkerPayment::class);
    }

    public function workDays(): HasMany
    {
        return $this->hasMany(WorkerWorkDay::class)->orderBy('work_date');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function expenses(): MorphMany
    {
        return $this->morphMany(ProjectExpense::class, 'reference');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('settled_at');
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * Advances taken before Saturday that the worker agreed to have taken off
     * this week's wage.
     */
    public function advancesDeducted(): float
    {
        return $this->sumPayments(
            fn (WorkerPayment $payment) => $payment->isAdvance() && $payment->deduct_from_week,
        );
    }

    /**
     * Advances taken before Saturday that were NOT deducted, so they became
     * debt carried into later weeks.
     */
    public function advancesToDebt(): float
    {
        return $this->sumPayments(
            fn (WorkerPayment $payment) => $payment->isAdvance() && ! $payment->deduct_from_week,
        );
    }

    public function settlementsPaid(): float
    {
        return $this->sumPayments(fn (WorkerPayment $payment) => $payment->isSettlement());
    }

    /**
     * What the worker is owed on Saturday, after this week's deductible
     * advances and any old debt being recovered.
     */
    public function netPayable(): float
    {
        return round(
            (float) $this->weekly_salary - $this->advancesDeducted() - (float) $this->debt_deducted,
            2,
        );
    }

    /**
     * Still to hand over. Never negative: paying more than the wage is change
     * owed back, not a bigger wage.
     */
    public function remainingSalary(): float
    {
        return round(max($this->netPayable() - $this->settlementsPaid(), 0), 2);
    }

    /**
     * Cash the worker actually received this week, advances included.
     */
    public function totalPaid(): float
    {
        return round($this->advancesDeducted() + $this->advancesToDebt() + $this->settlementsPaid(), 2);
    }

    public function label(): string
    {
        return $this->week_start->format('d/m/Y').' — '.$this->week_end->format('d/m/Y');
    }

    private function sumPayments(callable $filter): float
    {
        return round((float) $this->payments->filter($filter)->sum('amount'), 2);
    }
}
