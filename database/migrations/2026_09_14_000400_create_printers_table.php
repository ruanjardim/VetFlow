<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 30);
            $table->string('purpose', 30);
            $table->string('connection_type', 30);
            $table->string('paper_size', 20);
            $table->string('queue_name', 160)->nullable();
            $table->string('network_host')->nullable();
            $table->unsignedSmallInteger('network_port')->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 120)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'active']);
            $table->index(['clinic_id', 'is_default']);
            $table->index(['clinic_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
