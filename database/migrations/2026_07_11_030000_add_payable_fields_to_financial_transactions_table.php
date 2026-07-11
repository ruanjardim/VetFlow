<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->after('id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->after('clinic_id')->index();
            $table->unsignedBigInteger('purchase_entry_id')->nullable()->after('supplier_id')->index();
            $table->string('payment_method')->nullable()->after('status');
            $table->string('reference')->nullable()->after('payment_method');
            $table->text('notes')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex(['clinic_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['purchase_entry_id']);
            $table->dropColumn([
                'clinic_id',
                'supplier_id',
                'purchase_entry_id',
                'payment_method',
                'reference',
                'notes',
            ]);
        });
    }
};
