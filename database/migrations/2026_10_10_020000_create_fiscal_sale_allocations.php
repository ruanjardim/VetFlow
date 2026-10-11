<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_sale_allocations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete()->unique();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('input_fingerprint', 64);
            $table->json('source_snapshot');
            $table->json('allocation_snapshot');
            $table->dateTime('approved_at');
            $table->timestamps();

            $table->index(['clinic_id', 'approved_at']);
        });

        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->foreignId('fiscal_sale_allocation_id')
                ->nullable()
                ->after('sale_id')
                ->constrained('fiscal_sale_allocations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fiscal_sale_allocation_id');
        });

        Schema::dropIfExists('fiscal_sale_allocations');
    }
};
