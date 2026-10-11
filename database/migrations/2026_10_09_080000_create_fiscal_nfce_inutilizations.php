<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_nfce_inutilizations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 60);
            $table->string('environment', 20);
            $table->string('status', 30)->default('queued');
            $table->string('issuer_document', 14);
            $table->string('credential_reference', 160);
            $table->unsignedSmallInteger('series');
            $table->unsignedInteger('number_start');
            $table->unsignedInteger('number_end');
            $table->string('justification', 255);
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->string('protocol', 80)->nullable();
            $table->string('xml_path', 2000)->nullable();
            $table->string('last_error_code', 60)->nullable();
            $table->text('last_error_message')->nullable();
            $table->dateTime('queued_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'created_at'], 'fiscal_nfce_inutilizations_status_index');
            $table->index(['clinic_id', 'series', 'number_start', 'number_end'], 'fiscal_nfce_inutilizations_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_nfce_inutilizations');
    }
};
