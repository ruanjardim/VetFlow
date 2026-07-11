<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('clinic_id')->index();
            $table->unsignedBigInteger('seller_user_id')->nullable()->after('financial_transaction_id')->index();
            $table->string('source', 40)->default('pdv')->after('seller_user_id')->index();
            $table->decimal('additions_total', 10, 2)->default(0)->after('discount_total');
            $table->decimal('cost_total', 10, 2)->default(0)->after('change_total');
            $table->decimal('gross_profit_total', 10, 2)->default(0)->after('cost_total');
            $table->decimal('gross_margin_percent', 7, 2)->nullable()->after('gross_profit_total');
            $table->decimal('return_total', 10, 2)->default(0)->after('gross_margin_percent');
            $table->decimal('refunded_total', 10, 2)->default(0)->after('return_total');
            $table->dateTime('completed_at')->nullable()->after('sold_at')->index();
            $table->dateTime('cancelled_at')->nullable()->after('completed_at')->index();
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->json('metadata')->nullable()->after('notes');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('product_id')->index();
            $table->string('sku')->nullable()->after('barcode')->index();
            $table->string('product_name_snapshot')->nullable()->after('description')->index();
            $table->string('brand_snapshot')->nullable()->after('product_name_snapshot')->index();
            $table->string('category_snapshot')->nullable()->after('brand_snapshot')->index();
            $table->string('manufacturer_snapshot')->nullable()->after('category_snapshot')->index();
            $table->string('unit_snapshot', 20)->nullable()->after('manufacturer_snapshot');
            $table->decimal('cost_unit_price', 10, 2)->default(0)->after('unit_price');
            $table->decimal('original_unit_price', 10, 2)->default(0)->after('cost_unit_price');
            $table->decimal('discount_total', 10, 2)->default(0)->after('original_unit_price');
            $table->decimal('gross_total', 10, 2)->default(0)->after('discount_total');
            $table->decimal('net_total', 10, 2)->default(0)->after('gross_total');
            $table->decimal('gross_profit_total', 10, 2)->default(0)->after('net_total');
            $table->decimal('gross_margin_percent', 7, 2)->nullable()->after('gross_profit_total');
            $table->decimal('returned_quantity', 10, 3)->default(0)->after('gross_margin_percent');
            $table->decimal('refunded_total', 10, 2)->default(0)->after('returned_quantity');
            $table->json('metadata')->nullable()->after('refunded_total');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('installments')->default(1)->after('amount');
            $table->string('card_brand')->nullable()->after('installments')->index();
            $table->string('acquirer')->nullable()->after('card_brand')->index();
            $table->string('transaction_reference')->nullable()->after('reference')->index();
            $table->string('status', 30)->default('paid')->after('transaction_reference')->index();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->after('product_id')->index();
            $table->unsignedBigInteger('sale_item_id')->nullable()->after('sale_id')->index();
            $table->decimal('balance_before', 12, 3)->nullable()->after('unit_cost');
            $table->string('source', 40)->default('manual')->after('reason')->index();
            $table->json('metadata')->nullable()->after('notes');
        });

        Schema::create('sale_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->index();
            $table->unsignedBigInteger('sale_item_id')->nullable()->index();
            $table->unsignedBigInteger('inventory_movement_id')->nullable()->index();
            $table->string('event_type', 40)->index();
            $table->decimal('quantity', 10, 3)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_events');

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn([
                'sale_id',
                'sale_item_id',
                'balance_before',
                'source',
                'metadata',
            ]);
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropColumn([
                'installments',
                'card_brand',
                'acquirer',
                'transaction_reference',
                'status',
            ]);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'barcode',
                'sku',
                'product_name_snapshot',
                'brand_snapshot',
                'category_snapshot',
                'manufacturer_snapshot',
                'unit_snapshot',
                'cost_unit_price',
                'original_unit_price',
                'discount_total',
                'gross_total',
                'net_total',
                'gross_profit_total',
                'gross_margin_percent',
                'returned_quantity',
                'refunded_total',
                'metadata',
            ]);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'unit_id',
                'seller_user_id',
                'source',
                'additions_total',
                'cost_total',
                'gross_profit_total',
                'gross_margin_percent',
                'return_total',
                'refunded_total',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
                'metadata',
            ]);
        });
    }
};
