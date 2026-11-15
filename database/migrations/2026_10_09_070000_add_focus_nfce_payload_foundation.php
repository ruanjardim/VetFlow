<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_fiscal_profiles', function (Blueprint $table): void {
            $table->string('ibs_cbs_tax_status', 3)
                ->nullable()
                ->after('tax_benefit_code');
        });

        Schema::create('fiscal_nfce_sequences', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('provider', 60);
            $table->string('environment', 20);
            $table->unsignedSmallInteger('series');
            $table->unsignedInteger('next_number');
            $table->unsignedInteger('last_reserved_number')->nullable();
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'provider', 'environment', 'series'],
                'fiscal_nfce_sequences_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_nfce_sequences');

        Schema::table('product_fiscal_profiles', function (Blueprint $table): void {
            $table->dropColumn('ibs_cbs_tax_status');
        });
    }
};
