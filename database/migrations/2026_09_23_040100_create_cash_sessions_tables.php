<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One cash session per operator: opened with a change float, closed by
        // the operator with counted totals and reviewed by a manager.
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('status', 20)->default('open');
            $table->dateTime('opened_at');
            $table->decimal('opening_amount', 10, 2)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable()->index();
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('counted_cash', 10, 2)->default(0);
            $table->decimal('expected_total', 10, 2)->default(0);
            $table->decimal('counted_total', 10, 2)->default(0);
            $table->decimal('difference_total', 10, 2)->default(0);
            $table->decimal('cash_left', 10, 2)->nullable();
            $table->json('closing_snapshot')->nullable();
            $table->text('closing_notes')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->text('review_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'user_id', 'status']);
            $table->index('opened_at');
        });

        // Money moved inside a session outside of sale receipts: supply
        // (suprimento), withdrawal (sangria), expense (despesa), sale refunds
        // and customer credit deposits/refunds.
        Schema::create('cash_session_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('method', 30)->default('cash');
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->unsignedBigInteger('financial_transaction_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cash_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
