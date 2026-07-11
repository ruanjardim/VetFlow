<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->string('code')->unique();
            $table->string('status')->default('received')->index();
            $table->string('invoice_number')->nullable()->index();
            $table->string('invoice_key')->nullable()->index();
            $table->dateTime('purchased_at')->nullable()->index();
            $table->dateTime('received_at')->nullable()->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('clinic_id');
            $table->index('supplier_id');
        });

        Schema::create('purchase_entry_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_entry_id')
                ->constrained('purchase_entries')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('inventory_movement_id')
                ->nullable()
                ->constrained('inventory_movements')
                ->nullOnDelete();

            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('lot_number')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('purchase_entry_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_entry_items');
        Schema::dropIfExists('purchase_entries');
    }
};
