<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_period_closures', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('reviewer_name', 120);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('evidence_fingerprint', 64);
            $table->json('evidence_snapshot');
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['clinic_id', 'period_from', 'period_to', 'occurred_at'], 'fiscal_period_closures_period_index');
            $table->index(['clinic_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_period_closures');
    }
};
