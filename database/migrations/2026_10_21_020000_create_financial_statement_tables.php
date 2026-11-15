<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnDelete();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('format', 10);
            $table->string('file_hash', 64);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->timestamps();

            $table->unique(['financial_account_id', 'file_hash'], 'financial_statement_imports_account_hash_unique');
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::create('financial_statement_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnDelete();
            $table->foreignId('financial_statement_import_id')->constrained('financial_statement_imports')->cascadeOnDelete();
            $table->date('posted_at');
            $table->string('description', 255);
            $table->decimal('amount', 14, 2);
            $table->string('external_id', 120)->nullable();
            $table->string('fingerprint', 64);
            $table->timestamps();

            $table->unique(['financial_account_id', 'fingerprint'], 'financial_statement_entries_account_fingerprint_unique');
            $table->index(['clinic_id', 'financial_account_id', 'posted_at'], 'financial_statement_entries_scope_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_statement_entries');
        Schema::dropIfExists('financial_statement_imports');
    }
};
