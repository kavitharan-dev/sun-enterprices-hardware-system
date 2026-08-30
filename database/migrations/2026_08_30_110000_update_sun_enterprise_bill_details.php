<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('company_address', 'Ward No 03, Nilaweli, Trincomalee');
        Setting::set('company_phone', '0750683828 / 0756422450');

        foreach (['company_address', 'company_phone'] as $key) {
            Cache::forget("setting.{$key}");
        }
    }

    public function down(): void
    {
        Setting::set('company_address', 'Nilaweli, Trincomalee');
        Setting::set('company_phone', '+94781440789');

        foreach (['company_address', 'company_phone'] as $key) {
            Cache::forget("setting.{$key}");
        }
    }
};
