<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_clinical_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->string('title', 160);
            $table->text('details')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id', 'status'], 'patient_clinical_alerts_patient_status_index');
            $table->index(['clinic_id', 'status'], 'patient_clinical_alerts_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_clinical_alerts');
    }
};
