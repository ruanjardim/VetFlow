<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->string('description')->nullable();

            $table->boolean('system')->default(false);
            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['clinic_id', 'slug']);
            $table->index('active');
            $table->index('system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};