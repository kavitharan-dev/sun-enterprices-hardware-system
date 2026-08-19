<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_account_entries', function (Blueprint $table) {
            $table->string('transaction_no', 20)->nullable()->unique();
            $table->foreignId('cashier_request_id')
                ->nullable()
                ->unique()
                ->constrained('cashier_requests')
                ->nullOnDelete();
        });

        foreach (DB::table('daily_account_entries')->orderBy('id')->get() as $entry) {
            if ($entry->transaction_no) {
                continue;
            }

            DB::table('daily_account_entries')->where('id', $entry->id)->update([
                'transaction_no' => sprintf('TXN-%06d', $entry->id),
            ]);
        }

        foreach (['payments', 'worker_payments', 'project_owner_payments', 'project_expenses', 'purchases'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('daily_account_entry_id')
                    ->nullable()
                    ->constrained('daily_account_entries')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payments', 'worker_payments', 'project_owner_payments', 'project_expenses', 'purchases'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('daily_account_entry_id');
            });
        }

        Schema::table('daily_account_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_request_id');
            $table->dropUnique(['transaction_no']);
            $table->dropColumn('transaction_no');
        });
    }
};
