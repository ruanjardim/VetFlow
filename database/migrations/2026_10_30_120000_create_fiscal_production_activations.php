<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_production_activations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('homologation_review_id')->nullable()->constrained('fiscal_homologation_reviews')->nullOnDelete();
            $table->unsignedInteger('profile_version');
            $table->string('evidence_fingerprint', 64);
            $table->json('homologation_profile_snapshot');
            $table->string('reviewer_name', 120);
            $table->text('notes')->nullable();
            $table->dateTime('activated_at');
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'revoked_at', 'activated_at'], 'fiscal_production_activation_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_production_activations');
    }
};
