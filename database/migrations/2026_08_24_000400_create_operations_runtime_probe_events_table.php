<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_runtime_probe_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('environment', 40);
            $table->char('release_sha', 40);
            $table->char('probe_id', 26);
            $table->string('event', 30);
            $table->string('queue_connection', 40);
            $table->string('queue_mode', 20);
            $table->string('storage_disk', 80);
            $table->string('detail', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(
                ['environment', 'release_sha', 'clinic_id', 'occurred_at'],
                'operations_runtime_probe_scope_index'
            );
            $table->index(['probe_id', 'occurred_at'], 'operations_runtime_probe_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_runtime_probe_events');
    }
};
