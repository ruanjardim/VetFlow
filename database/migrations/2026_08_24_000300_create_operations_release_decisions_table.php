<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_release_decisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('environment', 40);
            $table->char('release_sha', 40);
            $table->string('decision', 20);
            $table->json('evidence_snapshot');
            $table->char('evidence_hash', 64);
            $table->string('note', 1000)->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(
                ['environment', 'release_sha', 'clinic_id', 'decided_at'],
                'operations_release_decision_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_release_decisions');
    }
};
