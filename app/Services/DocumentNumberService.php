<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $settingKey, string $defaultPrefix, string $modelClass, string $column = 'reference_no'): string
    {
        $prefix = Setting::get($settingKey, $defaultPrefix);
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-%";

        return DB::transaction(function () use ($modelClass, $column, $prefix, $year, $pattern) {
            /** @var Model $modelClass */
            $latest = $modelClass::query()
                ->withTrashed()
                ->where($column, 'like', $pattern)
                ->lockForUpdate()
                ->orderByDesc($column)
                ->value($column);

            $sequence = 1;

            if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
        });
    }
}
