<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('title', 120);
            $table->string('category', 120)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('opened_at')->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'opened_at']);
        });

        Schema::create('inventory_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('expected_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('variance_quantity', 15, 3)->nullable();
            $table->decimal('unit_cost_snapshot', 15, 2)->default(0);
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'product_id']);
            $table->unique('adjustment_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
    }
};
