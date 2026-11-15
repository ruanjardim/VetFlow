<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('scale_code', 12)->nullable()->after('sku');
            $table->index(['clinic_id', 'scale_code']);
        });

        Schema::create('pdv_scale_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained('clinics')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('label_prefix', 4)->default('20');
            $table->unsignedTinyInteger('product_code_digits')->default(5);
            $table->unsignedTinyInteger('weight_decimals')->default(3);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_scale_settings');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'scale_code']);
            $table->dropColumn('scale_code');
        });
    }
};
