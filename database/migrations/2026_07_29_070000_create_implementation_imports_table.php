<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementation_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('clinic_name');
            $table->string('user_name');
            $table->string('entity_type', 32);
            $table->string('entity_label', 80);
            $table->string('data_source', 32);
            $table->string('file_name');
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('imported_count');
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(
                ['clinic_id', 'completed_at'],
                'implementation_imports_clinic_completed_index'
            );
            $table->index(
                ['user_id', 'completed_at'],
                'implementation_imports_user_completed_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_imports');
    }
};
