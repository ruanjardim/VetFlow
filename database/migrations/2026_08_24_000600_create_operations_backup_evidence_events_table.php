<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_backup_evidence_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('environment', 40);
            $table->char('release_sha', 40);
            $table->string('backup_identifier', 120);
            $table->string('status', 20);
            $table->unsignedSmallInteger('checks_count');
            $table->char('evidence_sha256', 64);
            $table->timestamp('verified_at');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(
                ['environment', 'release_sha', 'occurred_at'],
                'operations_backup_evidence_scope_index'
            );
            $table->index('evidence_sha256', 'operations_backup_evidence_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_backup_evidence_events');
    }
};
