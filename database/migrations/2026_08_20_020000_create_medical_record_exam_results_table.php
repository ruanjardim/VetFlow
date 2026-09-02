<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_exam_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('medical_record_exam_id')->unique()->constrained('medical_record_exams')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('resulted_at')->nullable();
            $table->string('laboratory_name', 160)->nullable();
            $table->text('result_summary')->nullable();
            $table->longText('result_details')->nullable();
            $table->text('reference_notes')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'resulted_at'], 'exam_results_clinic_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_exam_results');
    }
};
