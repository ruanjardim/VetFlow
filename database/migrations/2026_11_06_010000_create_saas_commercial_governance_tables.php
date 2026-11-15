<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40);
            $table->string('title');
            $table->string('version', 40);
            $table->string('status', 20)->default('draft')->index();
            $table->longText('body');
            $table->string('content_hash', 64);
            $table->dateTime('effective_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'version'], 'saas_legal_documents_type_version_unique');
            $table->index(['type', 'status', 'published_at'], 'saas_legal_documents_public_index');
        });

        Schema::create('saas_coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->unsignedTinyInteger('percentage');
            $table->boolean('active')->default(true)->index();
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->unsignedSmallInteger('duration_months')->default(1);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('saas_coupon_plan', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('saas_coupons')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('saas_plans')->cascadeOnDelete();
            $table->primary(['coupon_id', 'plan_id']);
        });

        Schema::create('saas_subscription_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('saas_coupons')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('saas_subscriptions')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $table->string('coupon_code', 64);
            $table->unsignedTinyInteger('percentage');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('final_price_cents');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->dateTime('applied_at');
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'starts_on', 'ends_on'], 'saas_promotions_subscription_period_index');
            $table->index(['coupon_id', 'cancelled_at'], 'saas_promotions_coupon_usage_index');
        });

        Schema::create('saas_legal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('acceptance_uuid')->index();
            $table->foreignId('legal_document_id')->constrained('saas_legal_documents')->restrictOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name');
            $table->string('signer_email');
            $table->string('company_document', 20)->nullable();
            $table->string('document_type', 40);
            $table->string('document_version', 40);
            $table->string('document_hash', 64);
            $table->foreignId('plan_id')->nullable()->constrained('saas_plans')->nullOnDelete();
            $table->string('plan_name')->nullable();
            $table->string('billing_cycle', 20)->default('monthly');
            $table->unsignedBigInteger('base_price_cents')->nullable();
            $table->string('coupon_code', 64)->nullable();
            $table->unsignedTinyInteger('discount_percentage')->nullable();
            $table->unsignedBigInteger('final_price_cents')->nullable();
            $table->date('promotion_starts_on')->nullable();
            $table->date('promotion_ends_on')->nullable();
            $table->json('evidence')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('accepted_at');
            $table->timestamps();
            $table->index(['legal_document_id', 'clinic_id', 'user_id'], 'saas_legal_acceptances_document_clinic_user_index');
            $table->index(['clinic_id', 'accepted_at'], 'saas_legal_acceptances_clinic_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_legal_acceptances');
        Schema::dropIfExists('saas_subscription_promotions');
        Schema::dropIfExists('saas_coupon_plan');
        Schema::dropIfExists('saas_coupons');
        Schema::dropIfExists('saas_legal_documents');
    }
};
