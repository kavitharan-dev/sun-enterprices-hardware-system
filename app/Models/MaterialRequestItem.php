<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestItem extends Model
{
    protected $fillable = [
        'material_request_id',
        'product_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_issued',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:3',
            'quantity_approved' => 'decimal:3',
            'quantity_issued' => 'decimal:3',
        ];
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingToIssue(): float
    {
        return round(max((float) $this->quantity_approved - (float) $this->quantity_issued, 0), 3);
    }
}
