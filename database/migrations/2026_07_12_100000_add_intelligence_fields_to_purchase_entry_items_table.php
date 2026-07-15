<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_entry_items', function (Blueprint $table) {
            $table->string('barcode_snapshot')->nullable()->index();
            $table->string('supplier_sku')->nullable()->index();
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('margin_percent', 7, 2)->nullable();
            $table->boolean('update_sale_price')->default(false);
            $table->decimal('minimum_stock_after_entry', 12, 3)->nullable();
            $table->string('intelligence_status')->nullable()->index();
            $table->json('intelligence_metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_entry_items', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_snapshot',
                'supplier_sku',
                'sale_price',
                'margin_percent',
                'update_sale_price',
                'minimum_stock_after_entry',
                'intelligence_status',
                'intelligence_metadata',
            ]);
        });
    }
};
