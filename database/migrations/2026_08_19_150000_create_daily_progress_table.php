<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('progress_date');
            $table->text('work_completed');
            $table->unsignedInteger('workers_present')->default(0);
            $table->decimal('progress_percentage', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'progress_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_progress');
    }
};
