<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_fiscal_profiles', function (Blueprint $table): void {
            $table->string('iss_taxation', 1)->nullable();
            $table->string('ibs_cbs_tax_status', 3)->nullable();
        });

        Schema::create('fiscal_dps_sequences', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('provider', 60);
            $table->string('document_type', 10);
            $table->string('environment', 20);
            $table->string('series', 20);
            $table->unsignedBigInteger('next_number');
            $table->unsignedBigInteger('last_reserved_number')->nullable();
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'provider', 'document_type', 'environment', 'series'],
                'fiscal_dps_sequences_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_dps_sequences');

        Schema::table('service_fiscal_profiles', function (Blueprint $table): void {
            $table->dropColumn(['iss_taxation', 'ibs_cbs_tax_status']);
        });
    }
};
