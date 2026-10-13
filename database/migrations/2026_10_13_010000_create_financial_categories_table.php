<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->string('system_code', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'system_code']);
            $table->index(['clinic_id', 'type', 'active', 'sort_order']);
        });

        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->foreignId('financial_category_id')
                ->nullable()
                ->after('purchase_entry_id')
                ->constrained('financial_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('financial_category_id');
        });

        Schema::dropIfExists('financial_categories');
    }
};
