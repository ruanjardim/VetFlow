<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->uuid('client_request_id')->nullable()->after('code');
            $table->timestamp('offline_captured_at')->nullable()->after('sold_at');
            $table->timestamp('offline_synced_at')->nullable()->after('offline_captured_at');
            $table->uuid('offline_device_id')->nullable()->after('offline_synced_at');

            $table->unique('client_request_id', 'sales_client_request_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique('sales_client_request_id_unique');
            $table->dropColumn([
                'client_request_id',
                'offline_captured_at',
                'offline_synced_at',
                'offline_device_id',
            ]);
        });
    }
};
