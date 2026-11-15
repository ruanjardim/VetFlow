<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitalization_evolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('hospitalization_id')->constrained('hospitalizations')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('observed_at');
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->text('notes');
            $table->timestamps();

            $table->index(['clinic_id', 'hospitalization_id', 'observed_at'], 'hospitalization_evolutions_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitalization_evolutions');
    }
};
