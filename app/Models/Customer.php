<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'nic',
        'credit_limit',
        'outstanding_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function priorOutstanding(?Sale $exclude = null): float
    {
        $query = $this->sales()
            ->where('status', SaleStatus::Completed)
            ->where('balance', '>', 0);

        if ($exclude) {
            $query->where('id', '!=', $exclude->id);
        }

        return round((float) $query->sum('balance'), 2);
    }

    public function refreshOutstandingBalance(): void
    {
        $outstanding = $this->sales()
            ->where('status', SaleStatus::Completed)
            ->sum('balance');

        $this->update(['outstanding_balance' => round((float) $outstanding, 2)]);
    }
}
