<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete();
            $table->foreignId('seller_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->string('basis', 30);
            $table->string('recognition', 30);
            $table->boolean('requires_paid')->default(true);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'seller_user_id', 'active']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
