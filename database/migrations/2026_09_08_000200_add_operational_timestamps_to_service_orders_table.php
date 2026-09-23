<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dateTime('started_at')->nullable()->after('scheduled_at');
            $table->dateTime('ready_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn(['started_at', 'ready_at']);
        });
    }
};
