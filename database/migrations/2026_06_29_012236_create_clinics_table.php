<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {

            $table->id();

            $table->ulid('ulid')->unique();

            $table->foreignId('parent_clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->string('corporate_name');
            $table->string('trade_name');
            $table->string('cnpj')->unique();
            $table->string('crmv')->nullable();
            $table->string('technical_manager')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('website')->nullable();

            $table->string('zip_code')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();

            $table->string('logo')->nullable();

            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('currency', 3)->default('BRL');
            $table->string('language')->default('pt_BR');

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('active');
            $table->index('city');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};