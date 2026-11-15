<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete()->unique();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 20)->default('homologation');
            $table->string('provider', 60)->nullable();
            $table->boolean('nfce_enabled')->default(false);
            $table->boolean('nfse_enabled')->default(false);
            $table->string('state_registration', 30)->nullable();
            $table->string('municipal_registration', 30)->nullable();
            $table->string('tax_regime', 40)->nullable();
            $table->string('municipality_code', 7)->nullable();
            $table->string('cnae', 10)->nullable();
            $table->string('certificate_reference', 160)->nullable();
            $table->string('certificate_fingerprint', 128)->nullable();
            $table->dateTime('certificate_expires_at')->nullable();
            $table->unsignedInteger('configuration_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'enabled']);
        });

        Schema::create('fiscal_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10);
            $table->string('environment', 20);
            $table->string('provider', 60)->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedInteger('profile_version');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('issuer_snapshot');
            $table->json('recipient_snapshot')->nullable();
            $table->json('items_snapshot');
            $table->json('payments_snapshot');
            $table->json('totals_snapshot');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->string('access_key', 60)->nullable();
            $table->string('document_number', 30)->nullable();
            $table->string('series', 20)->nullable();
            $table->string('protocol', 80)->nullable();
            $table->string('last_error_code', 60)->nullable();
            $table->text('last_error_message')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('authorized_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'created_at']);
            $table->index(['clinic_id', 'type', 'created_at']);
            $table->index(['sale_id', 'type']);
        });

        Schema::create('fiscal_document_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained('fiscal_documents')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['clinic_id', 'occurred_at']);
            $table->index(['fiscal_document_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_events');
        Schema::dropIfExists('fiscal_documents');
        Schema::dropIfExists('fiscal_profiles');
    }
};
