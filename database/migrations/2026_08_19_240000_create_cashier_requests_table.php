<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('direction');
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->json('payload')->nullable();
            $table->string('method')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_requests');
    }
};
