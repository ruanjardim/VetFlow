<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('gtin', 32)->nullable()->after('barcode');
            $table->string('manufacturer')->nullable()->after('brand');
            $table->string('weight')->nullable()->after('unit');
            $table->string('image_path')->nullable()->after('weight');
            $table->string('lookup_source')->nullable()->after('image_path');
            $table->json('lookup_metadata')->nullable()->after('lookup_source');
            $table->dateTime('looked_up_at')->nullable()->after('lookup_metadata');

            $table->index('gtin');
        });

        DB::table('products')
            ->whereNull('gtin')
            ->whereNotNull('barcode')
            ->update(['gtin' => DB::raw('barcode')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['gtin']);
            $table->dropColumn([
                'gtin',
                'manufacturer',
                'weight',
                'image_path',
                'lookup_source',
                'lookup_metadata',
                'looked_up_at',
            ]);
        });
    }
};
