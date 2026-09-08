<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementation_pilot_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('clinic_name');
            $table->string('user_name');
            $table->string('check_key', 64);
            $table->string('check_label');
            $table->boolean('completed');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(
                ['clinic_id', 'check_key', 'decided_at'],
                'implementation_pilot_checks_latest_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_pilot_checks');
    }
};
