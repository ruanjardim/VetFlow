<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('unit_cost')->index();
            $table->date('expires_at')->nullable()->after('lot_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['lot_number']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['lot_number', 'expires_at']);
        });
    }
};
