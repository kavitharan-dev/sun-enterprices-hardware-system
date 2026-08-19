<?php

namespace App\Models;

use App\Enums\WorkerPaymentType;
use App\Enums\WorkerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'worker_code',
        'name',
        'nic',
        'phone',
        'job_role',
        'daily_rate',
        'weekly_salary',
        'join_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'weekly_salary' => 'decimal:2',
            'join_date' => 'date',
            'status' => WorkerStatus::class,
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_worker')
            ->withPivot(['role_on_site', 'assigned_from', 'assigned_to'])
            ->withTimestamps();
    }

    public function payrollWeeks(): HasMany
    {
        return $this->hasMany(WorkerPayrollWeek::class)->orderByDesc('week_start');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WorkerPayment::class);
    }

    public function workDays(): HasMany
    {
        return $this->hasMany(WorkerWorkDay::class);
    }

    /**
     * Advances the worker took without having them deducted from that week,
     * minus whatever has since been recovered out of a later week's wage.
     */
    public function debtBalance(): float
    {
        $incurred = (float) $this->payments()
            ->where('type', WorkerPaymentType::Advance)
            ->where('deduct_from_week', false)
            ->sum('amount');

        $recovered = (float) $this->payrollWeeks()->sum('debt_deducted');

        return round(max($incurred - $recovered, 0), 2);
    }

    public function hasDebt(): bool
    {
        return $this->debtBalance() > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WorkerStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === WorkerStatus::Active;
    }
}
