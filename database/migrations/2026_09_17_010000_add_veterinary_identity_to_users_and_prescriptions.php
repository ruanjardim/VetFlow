<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('veterinary_license_number', 30)->nullable()->after('position');
            $table->char('veterinary_license_state', 2)->nullable()->after('veterinary_license_number');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('responsible_veterinarian_id')
                ->nullable()
                ->after('medical_record_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('responsible_name')->nullable()->after('responsible_veterinarian_id');
            $table->string('responsible_license_number', 30)->nullable()->after('responsible_name');
            $table->char('responsible_license_state', 2)->nullable()->after('responsible_license_number');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_veterinarian_id');
            $table->dropColumn([
                'responsible_name',
                'responsible_license_number',
                'responsible_license_state',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'veterinary_license_number',
                'veterinary_license_state',
            ]);
        });
    }
};
