<?php

namespace App\Models;

use App\Enums\WorkerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'worker_code',
        'name',
        'nic',
        'phone',
        'job_role',
        'daily_rate',
        'join_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'join_date' => 'date',
            'status' => WorkerStatus::class,
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_worker')
            ->withPivot(['role_on_site', 'assigned_from', 'assigned_to'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WorkerStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === WorkerStatus::Active;
    }
}
