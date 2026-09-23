<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'size')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table): void {
            $table->string('size', 20)->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patients', 'size')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropColumn('size');
        });
    }
};
