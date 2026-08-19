<?php

namespace App\Models;

use App\Enums\MaterialIssueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialIssue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'issue_no',
        'project_id',
        'material_request_id',
        'issue_date',
        'issued_by',
        'total_cost',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'total_cost' => 'decimal:2',
            'status' => MaterialIssueStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialIssueItem::class);
    }

    public function expenses(): MorphMany
    {
        return $this->morphMany(ProjectExpense::class, 'reference');
    }

    public function itemsSummary(int $limit = 6): string
    {
        $this->loadMissing('items.product.unit');

        return \App\Support\ItemLineSummary::from($this->items, 'quantity', $limit);
    }
}
