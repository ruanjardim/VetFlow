<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_provider_credentials', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('configured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 60);
            $table->string('environment', 20);
            $table->string('reference', 160)->unique();
            $table->text('token')->nullable();
            $table->string('token_last_four', 4)->nullable();
            $table->dateTime('rotated_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'provider', 'environment', 'revoked_at'], 'fiscal_provider_credentials_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_provider_credentials');
    }
};
