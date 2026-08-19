<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function log(
        string $action,
        string $module,
        string $description,
        mixed $subject = null,
        ?array $properties = null,
        ?int $userId = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subject instanceof Model ? $subject::class : null,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'properties' => $properties,
            'ip_address' => request()->ip(),
        ]);
    }
}
