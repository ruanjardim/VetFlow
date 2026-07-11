<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->unique()->after('id');
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('ulid')
                ->constrained('clinics')
                ->nullOnDelete();
            $table->string('photo')->nullable()->after('phone');
            $table->string('position')->nullable()->after('photo');
            $table->timestamp('last_login_at')->nullable()->after('active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropColumn([
                'ulid',
                'photo',
                'position',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
