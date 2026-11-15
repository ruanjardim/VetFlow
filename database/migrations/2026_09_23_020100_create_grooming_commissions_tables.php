<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petshop_services', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('giant_price');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('grooming_commission_percent', 5, 2)->nullable()->after('grooming_professional');
        });

        Schema::create('grooming_commission_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total', 10, 2)->default(0);
            $table->unsignedInteger('entries_count')->default(0);
            $table->unsignedBigInteger('financial_transaction_id')->nullable()->index();
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'user_id']);
        });

        Schema::create('grooming_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('service_order_item_id')->nullable()->index();
            $table->foreignId('petshop_service_id')->nullable()->constrained('petshop_services')->nullOnDelete();
            $table->foreignId('reverses_id')->nullable()->constrained('grooming_commissions')->nullOnDelete();
            $table->string('kind', 20)->default('earning');
            $table->string('description');
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->dateTime('earned_at');
            $table->foreignId('settlement_id')->nullable()->constrained('grooming_commission_settlements')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'user_id', 'status']);
            $table->index(['service_order_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grooming_commissions');
        Schema::dropIfExists('grooming_commission_settlements');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('grooming_commission_percent');
        });
        Schema::table('petshop_services', function (Blueprint $table): void {
            $table->dropColumn('commission_percent');
        });
    }
};
