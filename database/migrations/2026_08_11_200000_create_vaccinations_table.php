<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vaccine_name');
            $table->string('manufacturer')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('status')->default('scheduled');
            $table->date('scheduled_for');
            $table->dateTime('applied_at')->nullable();
            $table->date('next_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'patient_id']);
            $table->index(['clinic_id', 'status', 'scheduled_for']);
            $table->index(['clinic_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
