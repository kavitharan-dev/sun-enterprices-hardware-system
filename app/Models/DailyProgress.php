<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyProgress extends Model
{
    protected $table = 'daily_progress';

    protected $fillable = [
        'project_id',
        'progress_date',
        'work_completed',
        'workers_present',
        'progress_percentage',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_date' => 'date',
            'progress_percentage' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
