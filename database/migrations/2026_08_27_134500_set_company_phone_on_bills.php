<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('company_phone', '+94781440789');
        Cache::forget('setting.company_phone');
    }

    public function down(): void
    {
        Setting::set('company_phone', '+94 11 234 5678');
        Cache::forget('setting.company_phone');
    }
};
