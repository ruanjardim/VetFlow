<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
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

            $table->foreignId('service_order_id')
                ->nullable()
                ->constrained('service_orders')
                ->nullOnDelete();

            $table->foreignId('financial_transaction_id')
                ->nullable()
                ->constrained('financial_transactions')
                ->nullOnDelete();

            $table->string('code')->unique();
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('pending');
            $table->dateTime('sold_at')->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('paid_total', 10, 2)->default(0);
            $table->decimal('change_total', 10, 2)->default(0);

            $table->boolean('stock_applied')->default(false);
            $table->boolean('financial_applied')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('clinic_id');
            $table->index('tutor_id');
            $table->index('patient_id');
            $table->index('service_order_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
