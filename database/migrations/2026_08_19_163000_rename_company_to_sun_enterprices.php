<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'company_name')
            ->whereIn('value', [
                'Sun Enterprise',
                'SUN ENTERPRISE',
                'SUN ENTERPRICES',
                'Sun Enterices',
                'SUN ENTERICES',
            ])
            ->update(['value' => 'Sun Enterprices', 'updated_at' => now()]);

        Cache::forget('setting.company_name');
    }

    public function down(): void
    {
        //
    }
};
