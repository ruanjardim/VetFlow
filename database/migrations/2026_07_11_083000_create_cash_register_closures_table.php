<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->unsignedBigInteger('closed_by_user_id')->nullable()->index();
            $table->dateTime('period_from')->index();
            $table->dateTime('period_to')->index();
            $table->dateTime('closed_at')->index();
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('counted_cash', 10, 2)->default(0);
            $table->decimal('cash_difference', 10, 2)->default(0);
            $table->decimal('expected_total', 10, 2)->default(0);
            $table->decimal('counted_total', 10, 2)->default(0);
            $table->decimal('total_difference', 10, 2)->default(0);
            $table->string('status', 30)->default('balanced')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_closures');
    }
};
