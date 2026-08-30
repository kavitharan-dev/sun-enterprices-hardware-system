<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_assets', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // tool | vehicle
            $table->string('name');
            $table->string('identifier')->nullable(); // plate no. or asset code
            $table->string('vehicle_kind')->nullable(); // tractor, lorry, etc.
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });

        Schema::create('store_asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('purpose')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_asset_id', 'returned_at']);
            $table->index(['worker_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_asset_assignments');
        Schema::dropIfExists('store_assets');
    }
};
