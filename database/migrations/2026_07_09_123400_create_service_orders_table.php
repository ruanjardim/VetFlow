<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();

            $table->foreignId('tutor_id')
                ->nullable()
                ->constrained('tutors')
                ->nullOnDelete();

            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->nullOnDelete();

            $table->string('code')->unique();
            $table->string('status')->default('open');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->text('notes')->nullable();

            $table->decimal('services_total', 10, 2)->default(0);
            $table->decimal('products_total', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('clinic_id');
            $table->index('tutor_id');
            $table->index('patient_id');
            $table->index('status');
            $table->index('opened_at');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
