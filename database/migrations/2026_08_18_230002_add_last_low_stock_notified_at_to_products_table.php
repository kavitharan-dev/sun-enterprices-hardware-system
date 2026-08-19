<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why this column is required:
     * Scheduled low-stock SMS must not resend on every cron run.
     * This timestamp records the last critical SMS so we can throttle alerts.
     * Existing product rows stay intact; the column is nullable.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('last_low_stock_notified_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('last_low_stock_notified_at');
        });
    }
};
