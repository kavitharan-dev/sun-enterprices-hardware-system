<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'brand_id',
        'unit_id',
        'description',
        'purchase_price',
        'selling_price',
        'min_stock_level',
        'stock_quantity',
        'is_active',
        'last_low_stock_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'min_stock_level' => 'decimal:3',
            'stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
            'last_low_stock_notified_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->min_stock_level;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    /**
     * Case-insensitive search by product name or SKU/barcode.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $inner) use ($like) {
            $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$like]);
        });
    }

    public function formatQuantity(): string
    {
        $qty = rtrim(rtrim(number_format((float) $this->stock_quantity, 3, '.', ''), '0'), '.');

        return $qty.' '.($this->unit?->symbol ?? '');
    }
}
