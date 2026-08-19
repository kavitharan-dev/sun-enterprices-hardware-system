<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Models\Concerns\HasFinancialTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFinancialTransaction;
    use SoftDeletes;

    protected $fillable = [
        'reference_no',
        'supplier_id',
        'purchase_date',
        'subtotal',
        'discount',
        'tax',
        'total',
        'status',
        'notes',
        'created_by',
        'completed_at',
        'daily_account_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => PurchaseStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status === PurchaseStatus::Completed;
    }
}
