<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('financial_statement_entry_id')->constrained('financial_statement_entries')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('match_kind', 30)->default('manual');
            $table->string('status', 20)->default('confirmed');
            $table->text('note')->nullable();
            $table->timestamp('reconciled_at');
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'reconciled_at']);
            $table->index(['financial_statement_entry_id', 'status']);
            $table->index(['financial_transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliations');
    }
};
