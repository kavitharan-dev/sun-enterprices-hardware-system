<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_account_days', function (Blueprint $table) {
            $table->boolean('is_closed')->default(false)->after('notes');
            $table->decimal('closing_balance', 14, 2)->nullable()->after('is_closed');
            $table->decimal('counted_cash', 14, 2)->nullable()->after('closing_balance');
            $table->text('close_notes')->nullable()->after('counted_cash');
            $table->timestamp('closed_at')->nullable()->after('close_notes');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_account_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn([
                'is_closed',
                'closing_balance',
                'counted_cash',
                'close_notes',
                'closed_at',
            ]);
        });
    }
};
