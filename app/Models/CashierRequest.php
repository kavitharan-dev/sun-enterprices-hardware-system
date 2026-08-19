<?php

namespace App\Models;

use App\Enums\CashierRequestStatus;
use App\Enums\CashierRequestType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashierRequest extends Model
{
    protected $fillable = [
        'type',
        'status',
        'direction',
        'amount',
        'description',
        'project_id',
        'worker_id',
        'subject_type',
        'subject_id',
        'payload',
        'method',
        'payment_date',
        'reference',
        'notes',
        'requested_by',
        'confirmed_by',
        'confirmed_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashierRequestType::class,
            'status' => CashierRequestStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'method' => PaymentMethod::class,
            'payment_date' => 'date',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function financialTransaction(): HasOne
    {
        return $this->hasOne(DailyAccountEntry::class, 'cashier_request_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CashierRequestStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === CashierRequestStatus::Pending;
    }
}
