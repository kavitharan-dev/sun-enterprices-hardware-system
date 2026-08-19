<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('tendered_amount', 12, 2)->default(0)->after('paid_amount');
            $table->decimal('change_amount', 12, 2)->default(0)->after('tendered_amount');
        });

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'company_name')
                ->whereIn('value', [
                    'Sun Enterprise',
                    'SUN ENTERPRISE',
                    'SUN ENTERPRICES',
                    'Sun Enterprices',
                    'SUN ENTERICES',
                ])
                ->update(['value' => 'Sun Enterices', 'updated_at' => now()]);

            DB::table('settings')
                ->where('key', 'company_address')
                ->whereIn('value', ['Sri Lanka', ''])
                ->update(['value' => 'Nilaveli, Trincomalee', 'updated_at' => now()]);

            Cache::forget('setting.company_name');
            Cache::forget('setting.company_address');
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tendered_amount', 'change_amount']);
        });
    }
};
