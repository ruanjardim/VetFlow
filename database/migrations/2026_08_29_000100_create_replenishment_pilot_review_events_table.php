<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replenishment_pilot_review_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_label');
            $table->string('period', 10)->index();
            $table->string('decision', 20)->index();
            $table->json('evidence_snapshot');
            $table->char('evidence_hash', 64)->index();
            $table->string('note', 500)->nullable();
            $table->timestamp('reviewed_at')->index();
            $table->timestamps();

            $table->index(
                ['clinic_id', 'period', 'reviewed_at'],
                'replenishment_pilot_review_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_pilot_review_events');
    }
};
