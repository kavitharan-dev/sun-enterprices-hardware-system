<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\HasFinancialTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFinancialTransaction;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'amount',
        'payment_method',
        'payment_date',
        'reference',
        'notes',
        'received_by',
        'daily_account_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_date' => 'date',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
