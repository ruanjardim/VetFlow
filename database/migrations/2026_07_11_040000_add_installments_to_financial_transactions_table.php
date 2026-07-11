<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger('installment_number')->default(1)->after('purchase_entry_id');
            $table->unsignedSmallInteger('installment_total')->default(1)->after('installment_number');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropColumn(['installment_number', 'installment_total']);
        });
    }
};
