<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('institution', 100)->nullable();
            $table->string('type', 30);
            $table->string('account_number_last4', 4)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
