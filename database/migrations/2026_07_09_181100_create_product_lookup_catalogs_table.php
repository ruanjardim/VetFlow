<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lookup_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('gtin', 32)->unique();
            $table->string('name')->nullable();
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('weight')->nullable();
            $table->string('image_path')->nullable();
            $table->text('image_url')->nullable();
            $table->string('source')->nullable();
            $table->string('lookup_status')->default('found');
            $table->json('metadata')->nullable();
            $table->json('source_payload')->nullable();
            $table->dateTime('last_lookup_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('brand');
            $table->index('category');
            $table->index('lookup_status');
            $table->index('last_lookup_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lookup_catalogs');
    }
};
