<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->json('grooming_schedule')->nullable()->after('language');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('grooming_schedule')->nullable()->after('grooming_commission_percent');
        });

        Schema::create('grooming_schedule_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'starts_at', 'ends_at'], 'grooming_blocks_clinic_period');
            $table->index(['user_id', 'starts_at'], 'grooming_blocks_user_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grooming_schedule_blocks');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('grooming_schedule');
        });

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('grooming_schedule');
        });
    }
};
