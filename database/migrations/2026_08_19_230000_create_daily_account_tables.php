<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_account_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('daily_account_entries', function (Blueprint $table) {
            $table->id();
            $table->date('occurred_on');
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no', 80)->nullable();
            $table->nullableMorphs('source');
            $table->string('method')->nullable();
            $table->decimal('income', 14, 2)->default(0);
            $table->decimal('expense', 14, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['occurred_on', 'type']);
            $table->index(['project_id', 'occurred_on']);
            $table->index(['worker_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_account_entries');
        Schema::dropIfExists('daily_account_days');
    }
};
