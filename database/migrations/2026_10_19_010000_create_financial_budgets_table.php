<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('financial_category_id')->constrained('financial_categories')->cascadeOnDelete();
            $table->date('competence');
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'financial_category_id', 'competence'],
                'financial_budgets_clinic_category_competence_unique'
            );
            $table->index(['clinic_id', 'competence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_budgets');
    }
};
