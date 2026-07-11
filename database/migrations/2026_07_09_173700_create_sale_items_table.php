<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->foreignId('petshop_service_id')
                ->nullable()
                ->constrained('petshop_services')
                ->nullOnDelete();

            $table->foreignId('service_order_item_id')
                ->nullable()
                ->constrained('service_order_items')
                ->nullOnDelete();

            $table->string('type')->default('product');
            $table->string('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('sale_id');
            $table->index('product_id');
            $table->index('petshop_service_id');
            $table->index('service_order_item_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
