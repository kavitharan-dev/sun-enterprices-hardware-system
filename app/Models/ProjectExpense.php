<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Models\Concerns\HasFinancialTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectExpense extends Model
{
    use HasFinancialTransaction;
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'category',
        'amount',
        'expense_date',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
        'daily_account_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin') || $user->canReviewMaterialRequests()) {
            return $query;
        }

        if ($user->hasRole('site_manager')) {
            return $query->whereHas('project', fn (Builder $project) => $project->where('site_manager_id', $user->id));
        }

        return $query->whereRaw('0 = 1');
    }

    public function isAutomatic(): bool
    {
        return $this->reference_id !== null || $this->category === ExpenseCategory::Material;
    }
}
