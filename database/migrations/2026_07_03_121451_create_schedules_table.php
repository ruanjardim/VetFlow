<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('tutors')->nullOnDelete();

            $table->string('title')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();

            $table->string('type')->nullable();
            $table->string('status')->default('agendado');

            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};