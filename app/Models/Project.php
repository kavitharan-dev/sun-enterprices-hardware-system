<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_code',
        'name',
        'customer_id',
        'location',
        'description',
        'budget',
        'start_date',
        'expected_end_date',
        'actual_end_date',
        'status',
        'progress_percentage',
        'site_manager_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'status' => ProjectStatus::class,
            'progress_percentage' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function siteManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'site_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'project_worker')
            ->withPivot(['id', 'role_on_site', 'assigned_from', 'assigned_to'])
            ->withTimestamps();
    }

    public function materialRequests(): HasMany
    {
        return $this->hasMany(MaterialRequest::class);
    }

    public function materialIssues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    public function dailyProgress(): HasMany
    {
        return $this->hasMany(DailyProgress::class);
    }

    public function totalSpent(): float
    {
        return round((float) $this->expenses()->sum('amount'), 2);
    }

    public function remainingBudget(): float
    {
        return round((float) $this->budget - $this->totalSpent(), 2);
    }

    public function budgetUsedPercent(): float
    {
        if ((float) $this->budget <= 0) {
            return 0;
        }

        return round(($this->totalSpent() / (float) $this->budget) * 100, 1);
    }

    public function materialSpend(): float
    {
        return round((float) $this->expenses()
            ->where('category', 'material')
            ->sum('amount'), 2);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        if ($user->hasRole('site_manager')) {
            return $query->where('site_manager_id', $user->id);
        }

        return $query->whereRaw('0 = 1');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Active);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->site_manager_id === $user->id;
    }
}
