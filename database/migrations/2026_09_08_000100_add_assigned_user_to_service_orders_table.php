<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};
