<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('sale_type', 30)->default('in_store')->after('source')->index();
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('additions_total');
            $table->text('delivery_address')->nullable()->after('notes');
            $table->unsignedBigInteger('sale_quote_id')->nullable()->after('pet_package_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['sale_type']);
            $table->dropIndex(['sale_quote_id']);
            $table->dropColumn(['sale_type', 'delivery_fee', 'delivery_address', 'sale_quote_id']);
        });
    }
};
