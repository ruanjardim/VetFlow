<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementation_pilot_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('clinic_name');
            $table->string('user_name');
            $table->unsignedInteger('revision');
            $table->string('release_owner', 150);
            $table->string('support_owner', 150);
            $table->date('planned_start_date')->nullable();
            $table->text('scope');
            $table->text('release_notes');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'revision'],
                'implementation_pilot_releases_revision_unique'
            );
            $table->index(
                ['clinic_id', 'recorded_at'],
                'implementation_pilot_releases_latest_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_pilot_releases');
    }
};
