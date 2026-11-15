<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_homologation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 40);
            $table->string('reviewer_name', 120);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('evidence_fingerprint', 64);
            $table->json('evidence_snapshot');
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['clinic_id', 'occurred_at']);
            $table->index(['clinic_id', 'decision', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_homologation_reviews');
    }
};
