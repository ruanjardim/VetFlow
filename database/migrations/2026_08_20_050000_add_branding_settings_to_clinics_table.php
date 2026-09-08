<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('brand_icon_mode', 20)->default('automatic')->after('logo');
            $table->string('brand_icon_key', 20)->default('generic')->after('brand_icon_mode');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn(['brand_icon_mode', 'brand_icon_key']);
        });
    }
};
