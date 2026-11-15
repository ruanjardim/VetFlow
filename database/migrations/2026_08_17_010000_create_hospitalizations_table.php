<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->foreignId('admitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('hospitalized');
            $table->string('accommodation')->nullable();
            $table->dateTime('admitted_at');
            $table->dateTime('expected_discharge_at')->nullable();
            $table->dateTime('discharged_at')->nullable();
            $table->text('admission_reason');
            $table->text('clinical_notes')->nullable();
            $table->text('discharge_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status', 'admitted_at']);
            $table->index(['clinic_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitalizations');
    }
};
