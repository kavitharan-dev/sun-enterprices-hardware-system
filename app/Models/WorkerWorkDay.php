<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerWorkDay extends Model
{
    protected $fillable = [
        'worker_id',
        'worker_payroll_week_id',
        'project_id',
        'work_date',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(WorkerPayrollWeek::class, 'worker_payroll_week_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dayName(): string
    {
        return $this->work_date->format('l');
    }

    public function siteName(): string
    {
        return $this->project?->name ?? 'Not assigned to a site';
    }
}
