<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'grooming_professional')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('grooming_professional')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'grooming_professional')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('grooming_professional');
        });
    }
};
