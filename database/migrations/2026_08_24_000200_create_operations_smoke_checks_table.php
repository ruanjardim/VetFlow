<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_smoke_checks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('environment', 40);
            $table->char('release_sha', 40);
            $table->string('check_key', 80);
            $table->boolean('completed');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(
                ['environment', 'release_sha', 'clinic_id', 'check_key', 'created_at'],
                'operations_smoke_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_smoke_checks');
    }
};
