<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_products', function (Blueprint $table) {
            $table->id();
            $table->string('gtin', 32)->unique();
            $table->string('ean', 32)->nullable()->index();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('name')->nullable()->index();
            $table->string('brand')->nullable()->index();
            $table->string('manufacturer')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->string('subcategory')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('weight')->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('package')->nullable();
            $table->string('species')->nullable()->index();
            $table->string('active_ingredient')->nullable()->index();
            $table->string('dosage')->nullable();
            $table->string('concentration')->nullable();
            $table->string('pharmaceutical_form')->nullable();
            $table->string('storage_temperature')->nullable();
            $table->boolean('prescription_required')->nullable();
            $table->string('registration_number')->nullable()->index();
            $table->string('country', 2)->nullable()->index();
            $table->string('api_source')->nullable()->index();
            $table->decimal('source_confidence', 5, 2)->default(0);
            $table->string('status', 20)->default('PENDING')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_lookup_at')->nullable()->index();
            $table->timestamps();

            $table->index(['category', 'subcategory']);
            $table->index(['brand', 'name']);
        });

        Schema::create('global_product_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->string('source_name')->index();
            $table->string('source_label')->nullable();
            $table->string('source_type', 30)->default('free')->index();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('status', 20)->default('PENDING')->index();
            $table->timestamp('queried_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['global_product_id', 'source_name']);
        });

        Schema::create('global_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->text('image_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_type', 40)->default('front');
            $table->string('source_name')->nullable()->index();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('global_product_regulatory_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->string('registration_number')->nullable()->index();
            $table->string('authority')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('active_ingredient')->nullable()->index();
            $table->string('dosage')->nullable();
            $table->string('concentration')->nullable();
            $table->string('pharmaceutical_form')->nullable();
            $table->string('storage_temperature')->nullable();
            $table->boolean('prescription_required')->nullable();
            $table->string('source_name')->nullable()->index();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('global_product_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('gtin', 32)->nullable()->index();
            $table->string('suggestion_type', 40)->default('manual')->index();
            $table->string('suggested_name')->nullable();
            $table->string('source_name')->nullable()->index();
            $table->string('status', 20)->default('PENDING')->index();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clinic_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('global_product_id')->nullable()->constrained('global_products')->nullOnDelete();
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->string('location')->nullable();
            $table->string('lot_number')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->string('taxation')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['clinic_id', 'global_product_id']);
            $table->index(['clinic_id', 'active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('global_product_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('global_products')
                ->nullOnDelete();
        });

        $this->copyExistingLookupCatalog();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('global_product_id');
        });

        Schema::dropIfExists('clinic_products');
        Schema::dropIfExists('global_product_suggestions');
        Schema::dropIfExists('global_product_regulatory_data');
        Schema::dropIfExists('global_product_images');
        Schema::dropIfExists('global_product_sources');
        Schema::dropIfExists('global_products');
    }

    private function copyExistingLookupCatalog(): void
    {
        if (! Schema::hasTable('product_lookup_catalogs')) {
            return;
        }

        DB::table('product_lookup_catalogs')
            ->where('lookup_status', 'found')
            ->orderBy('id')
            ->get()
            ->each(function ($item) {
                DB::table('global_products')->updateOrInsert(
                    ['gtin' => $item->gtin],
                    [
                        'ean' => $item->gtin,
                        'barcode' => $item->gtin,
                        'name' => $item->name,
                        'brand' => $item->brand,
                        'manufacturer' => $item->manufacturer,
                        'category' => $item->category,
                        'description' => $item->description,
                        'image_url' => $item->image_url,
                        'image_path' => $item->image_path,
                        'weight' => $item->weight,
                        'unit' => $item->unit,
                        'api_source' => $item->source,
                        'source_confidence' => $item->source ? 70 : 0,
                        'status' => 'PENDING',
                        'metadata' => $item->metadata,
                        'last_lookup_at' => $item->last_lookup_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }
};
