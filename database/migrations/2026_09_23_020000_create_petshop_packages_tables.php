<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petshop_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'active']);
        });

        Schema::create('petshop_package_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('petshop_package_id')->constrained('petshop_packages')->cascadeOnDelete();
            $table->foreignId('petshop_service_id')->constrained('petshop_services')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });

        Schema::create('pet_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('petshop_package_id')->nullable()->constrained('petshop_packages')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('tutors')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 30)->default('pending_payment');
            $table->date('starts_on');
            $table->date('expires_on')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status']);
            $table->index(['patient_id', 'status']);
        });

        Schema::create('pet_package_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_package_id')->constrained('pet_packages')->cascadeOnDelete();
            $table->foreignId('petshop_service_id')->nullable()->constrained('petshop_services')->nullOnDelete();
            $table->string('service_name');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_value', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pet_package_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_package_id')->constrained('pet_packages')->cascadeOnDelete();
            $table->foreignId('pet_package_balance_id')->constrained('pet_package_balances')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('service_order_item_id')->nullable()->index();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_value', 10, 2)->default(0);
            $table->dateTime('used_at');
            $table->timestamps();

            $table->index(['pet_package_balance_id', 'service_order_id']);
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->boolean('use_package_balance')->default(true)->after('recurrence_group');
        });

        Schema::table('service_order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('pet_package_balance_id')->nullable()->after('petshop_service_id')->index();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('pet_package_id')->nullable()->after('service_order_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['pet_package_id']);
            $table->dropColumn('pet_package_id');
        });
        Schema::table('service_order_items', function (Blueprint $table): void {
            $table->dropIndex(['pet_package_balance_id']);
            $table->dropColumn('pet_package_balance_id');
        });
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn('use_package_balance');
        });
        Schema::dropIfExists('pet_package_usages');
        Schema::dropIfExists('pet_package_balances');
        Schema::dropIfExists('pet_packages');
        Schema::dropIfExists('petshop_package_items');
        Schema::dropIfExists('petshop_packages');
    }
};
