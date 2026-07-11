<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petshop_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();

            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('small_price', 10, 2)->nullable();
            $table->decimal('medium_price', 10, 2)->nullable();
            $table->decimal('large_price', 10, 2)->nullable();
            $table->decimal('giant_price', 10, 2)->nullable();

            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('requires_appointment')->default(true);
            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('clinic_id');
            $table->index('name');
            $table->index('category');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petshop_services');
    }
};
