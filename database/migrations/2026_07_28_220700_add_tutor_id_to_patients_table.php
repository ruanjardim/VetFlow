<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->foreignId('tutor_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('tutors')
                ->nullOnDelete();

            $table->index(['clinic_id', 'tutor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'tutor_id']);
            $table->dropConstrainedForeignId('tutor_id');
        });
    }
};
