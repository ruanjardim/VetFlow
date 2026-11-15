<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_fiscal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->unique();
            $table->string('ncm', 8)->nullable();
            $table->string('cest', 7)->nullable();
            $table->string('origin_code', 1)->nullable();
            $table->string('default_cfop', 4)->nullable();
            $table->string('icms_code', 3)->nullable();
            $table->string('pis_code', 2)->nullable();
            $table->string('cofins_code', 2)->nullable();
            $table->string('fiscal_unit', 6)->nullable();
            $table->string('tax_benefit_code', 20)->nullable();
            $table->string('ibs_cbs_classification_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('ready')->default(false);
            $table->json('missing_fields')->nullable();
            $table->unsignedInteger('configuration_version')->default(1);
            $table->timestamps();

            $table->index(['clinic_id', 'ready']);
        });

        Schema::create('service_fiscal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('petshop_service_id')->constrained('petshop_services')->cascadeOnDelete()->unique();
            $table->string('national_taxation_code', 20)->nullable();
            $table->string('municipal_taxation_code', 30)->nullable();
            $table->string('service_list_item', 10)->nullable();
            $table->string('cnae', 7)->nullable();
            $table->string('nbs_code', 15)->nullable();
            $table->string('municipality_code', 7)->nullable();
            $table->decimal('iss_rate', 7, 4)->nullable();
            $table->boolean('iss_withheld')->default(false);
            $table->string('ibs_cbs_classification_code', 20)->nullable();
            $table->string('operation_indicator', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('ready')->default(false);
            $table->json('missing_fields')->nullable();
            $table->unsignedInteger('configuration_version')->default(1);
            $table->timestamps();

            $table->index(['clinic_id', 'ready']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fiscal_profiles');
        Schema::dropIfExists('product_fiscal_profiles');
    }
};
