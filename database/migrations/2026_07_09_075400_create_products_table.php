<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->string('name');
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();

            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->string('unit', 20)->default('un');

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('clinic_id');
            $table->index('name');
            $table->index('category');
            $table->index('brand');
            $table->index('barcode');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
