<?php

namespace App\Models;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DailyAccountEntry extends Model
{
    protected $fillable = [
        'transaction_no',
        'occurred_on',
        'type',
        'category',
        'description',
        'project_id',
        'worker_id',
        'reference_no',
        'source_type',
        'source_id',
        'cashier_request_id',
        'method',
        'income',
        'expense',
        'is_manual',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'type' => DailyAccountType::class,
            'category' => DailyAccountCategory::class,
            'method' => PaymentMethod::class,
            'income' => 'decimal:2',
            'expense' => 'decimal:2',
            'is_manual' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function cashierRequest(): BelongsTo
    {
        return $this->belongsTo(CashierRequest::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function net(): float
    {
        return round((float) $this->income - (float) $this->expense, 2);
    }
}
