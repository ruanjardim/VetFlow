<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_entry_id')->nullable()->after('sale_item_id')->index();
            $table->unsignedBigInteger('purchase_entry_item_id')->nullable()->after('purchase_entry_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['purchase_entry_id']);
            $table->dropIndex(['purchase_entry_item_id']);
            $table->dropColumn(['purchase_entry_id', 'purchase_entry_item_id']);
        });
    }
};
