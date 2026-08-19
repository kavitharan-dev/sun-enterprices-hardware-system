<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->decimal('weekly_salary', 12, 2)->default(0)->after('daily_rate');
        });

        // One row per worker per pay week. The week runs Sunday to Saturday and
        // is settled on Saturday, which is payday.
        Schema::create('worker_payroll_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            // Snapshot, so changing a worker's salary never rewrites paid weeks.
            $table->decimal('weekly_salary', 12, 2)->default(0);
            // Old debt recovered out of this week's wage.
            $table->decimal('debt_deducted', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['worker_id', 'week_start']);
            $table->index(['week_start', 'week_end']);
        });

        // Every rupee handed to a worker: advances taken before Saturday and the
        // Saturday settlement itself.
        Schema::create('worker_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_payroll_week_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            // Advance only: true reduces this week's wage, false becomes debt
            // that carries into later weeks.
            $table->boolean('deduct_from_week')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['worker_id', 'payment_date']);
            $table->index(['worker_id', 'type']);
        });

        // The weekly work sheet: which day the worker worked and on which site.
        Schema::create('worker_work_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_payroll_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('notes', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['worker_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_work_days');
        Schema::dropIfExists('worker_payments');
        Schema::dropIfExists('worker_payroll_weeks');

        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('weekly_salary');
        });
    }
};
