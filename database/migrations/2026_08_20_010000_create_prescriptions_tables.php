<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->dateTime('prescribed_at');
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('general_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'patient_id', 'prescribed_at']);
            $table->index(['clinic_id', 'status', 'prescribed_at']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('medication_name');
            $table->string('concentration')->nullable();
            $table->string('dosage');
            $table->string('route')->nullable();
            $table->string('frequency');
            $table->string('duration')->nullable();
            $table->string('quantity')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->unique(['prescription_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
