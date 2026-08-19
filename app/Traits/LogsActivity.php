<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Support\ActivityLogger;

trait LogsActivity
{
    protected function logActivity(
        string $action,
        string $module,
        string $description,
        mixed $subject = null,
        ?array $properties = null,
        ?int $userId = null,
    ): ActivityLog {
        return ActivityLogger::log(
            $action,
            $module,
            $description,
            $subject,
            $properties,
            $userId,
        );
    }
}
