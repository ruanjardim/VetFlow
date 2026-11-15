<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_commission_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('seller_user_id')->constrained('users')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('entries_count')->default(0);
            $table->foreignId('financial_transaction_id')
                ->nullable()
                ->constrained('financial_transactions')
                ->nullOnDelete();
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->char('snapshot_hash', 64)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'seller_user_id', 'period_end'], 'sales_commission_settlements_lookup');
        });

        Schema::create('sales_commission_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')
                ->constrained('sales_commission_settlements')
                ->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->constrained('commission_rules')->restrictOnDelete();
            $table->foreignId('seller_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('sale_payment_id')->nullable()->constrained('sale_payments')->restrictOnDelete();
            $table->string('source_key', 80);
            $table->unsignedInteger('revision')->default(1);
            $table->date('source_date');
            $table->string('sale_code', 80);
            $table->string('rule_name', 120);
            $table->string('basis', 30);
            $table->string('recognition', 30);
            $table->decimal('base_amount', 12, 2);
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 12, 2);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['commission_rule_id', 'source_key', 'revision'],
                'sales_commission_items_source_revision_unique'
            );
            $table->index(['clinic_id', 'seller_user_id', 'source_date'], 'sales_commission_items_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_settlement_items');
        Schema::dropIfExists('sales_commission_settlements');
    }
};
