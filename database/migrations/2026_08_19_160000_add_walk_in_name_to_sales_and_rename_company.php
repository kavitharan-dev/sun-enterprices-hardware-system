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
            $table->string('walk_in_name', 180)->nullable()->after('customer_id');
        });

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'company_name')
                ->whereIn('value', ['Sun Enterprise', 'SUN ENTERPRISE', ''])
                ->update(['value' => 'SUN ENTERPRICES', 'updated_at' => now()]);

            Cache::forget('setting.company_name');
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('walk_in_name');
        });
    }
};
