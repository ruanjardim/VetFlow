<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('payment_method_id')->nullable()->after('method')->index();
            $table->unsignedBigInteger('cash_session_id')->nullable()->after('payment_method_id')->index();
            $table->decimal('fee_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('net_amount', 10, 2)->nullable()->after('fee_amount');
            $table->date('expected_settlement_date')->nullable()->after('paid_at')->index();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('cash_session_id')->nullable()->after('seller_user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['cash_session_id']);
            $table->dropColumn('cash_session_id');
        });

        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->dropIndex(['payment_method_id']);
            $table->dropIndex(['cash_session_id']);
            $table->dropIndex(['expected_settlement_date']);
            $table->dropColumn(['payment_method_id', 'cash_session_id', 'fee_amount', 'net_amount', 'expected_settlement_date']);
        });
    }
};
