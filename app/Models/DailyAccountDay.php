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
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
