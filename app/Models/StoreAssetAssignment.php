<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAssetAssignment extends Model
{
    protected $fillable = [
        'store_asset_id',
        'worker_id',
        'project_id',
        'purpose',
        'issued_at',
        'returned_at',
        'issued_by',
        'returned_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(StoreAsset::class, 'store_asset_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }
}
