<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment methods per clinic: one row per card machine and type
        // ("Rede Crédito", "PagSeguro Débito"), plus cash, Pix and others.
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('name', 100);
            $table->string('kind', 30);
            $table->string('acquirer', 100)->nullable();
            $table->string('card_brand', 50)->nullable();
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->decimal('installment_fee_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('settlement_days')->default(0);
            $table->unsignedSmallInteger('max_installments')->default(1);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
