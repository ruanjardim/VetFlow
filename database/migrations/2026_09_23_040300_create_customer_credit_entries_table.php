<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer credit ledger (saldo credor): positive entries add credit
        // (deposit, change left as credit, return as credit), negative ones use
        // it (payment in a sale, credit given back in money). The debit side
        // (fiado) comes from sales with an outstanding balance.
        Schema::create('customer_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('tutor_id')->constrained('tutors')->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->unsignedBigInteger('sale_payment_id')->nullable()->index();
            $table->unsignedBigInteger('cash_session_id')->nullable()->index();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'tutor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_entries');
    }
};
