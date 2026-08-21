<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAccountDay extends Model
{
    protected $fillable = [
        'business_date',
        'opening_balance',
        'notes',
        'is_closed',
        'closing_balance',
        'counted_cash',
        'close_notes',
        'closed_at',
        'closed_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_balance' => 'decimal:2',
            'is_closed' => 'boolean',
            'closing_balance' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return (bool) $this->is_closed;
    }

    public function cashVariance(): ?float
    {
        if ($this->counted_cash === null || $this->closing_balance === null) {
            return null;
        }

        return round((float) $this->counted_cash - (float) $this->closing_balance, 2);
    }
}
