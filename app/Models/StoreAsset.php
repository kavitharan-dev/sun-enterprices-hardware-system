<?php

namespace App\Models;

use App\Enums\StoreAssetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'identifier',
        'vehicle_kind',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => StoreAssetType::class,
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StoreAssetAssignment::class)->latest('issued_at');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(StoreAssetAssignment::class)->whereNull('returned_at')->latest('issued_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTools(Builder $query): Builder
    {
        return $query->where('type', StoreAssetType::Tool);
    }

    public function scopeVehicles(Builder $query): Builder
    {
        return $query->where('type', StoreAssetType::Vehicle);
    }

    public function isOut(): bool
    {
        return $this->activeAssignment()->exists();
    }

    public function displayLabel(): string
    {
        $label = $this->name;

        if ($this->identifier) {
            $label .= ' — '.$this->identifier;
        }

        if ($this->vehicle_kind) {
            $label .= ' ('.$this->vehicle_kind.')';
        }

        return $label;
    }
}
