<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('name');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('location');
            $table->text('description')->nullable();
            $table->decimal('budget', 14, 2)->default(0);
            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('status')->default('planning');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->foreignId('site_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'site_manager_id']);
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('worker_code')->unique();
            $table->string('name');
            $table->string('nic')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_role')->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->date('join_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_worker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->string('role_on_site')->nullable();
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'worker_id', 'assigned_from']);
        });

        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('request_date');
            $table->date('required_date')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'project_id']);
        });

        Schema::create('material_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_requested', 12, 3);
            $table->decimal('quantity_approved', 12, 3)->default(0);
            $table->decimal('quantity_issued', 12, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('project_worker');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('projects');
    }
};
