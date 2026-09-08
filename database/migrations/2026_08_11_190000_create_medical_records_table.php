<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->foreignId('appointment_id')
                ->unique()
                ->constrained('appointments')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('examined_at');
            $table->text('chief_complaint')->nullable();
            $table->text('anamnesis')->nullable();
            $table->text('clinical_findings')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('prescription_notes')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->string('hydration')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'patient_id']);
            $table->index(['clinic_id', 'examined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
