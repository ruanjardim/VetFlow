<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->string('category', 80)->nullable();
            $table->boolean('system')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['clinic_id', 'normalized_name']);
        });

        Schema::create('animal_exam_species', function (Blueprint $table): void {
            $table->foreignId('animal_exam_id')->constrained('animal_exams')->cascadeOnDelete();
            $table->foreignId('animal_species_id')->constrained('animal_species')->cascadeOnDelete();
            $table->unique(['animal_exam_id', 'animal_species_id'], 'exam_species_unique');
        });

        Schema::create('medical_record_exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('animal_exam_id')->constrained('animal_exams')->restrictOnDelete();
            $table->string('exam_name', 160);
            $table->timestamps();
            $table->unique(['medical_record_id', 'animal_exam_id'], 'medical_record_exam_unique');
        });

        $now = now();
        $catalog = require database_path('data/animal_exam_catalog.php');

        DB::table('animal_exams')->insert(collect($catalog)->map(fn (array $entry): array => [
            'clinic_id' => null,
            'name' => $entry['name'],
            'normalized_name' => Str::of($entry['name'])->ascii()->lower()->squish()->value(),
            'category' => $entry['category'],
            'system' => true,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_exams');
        Schema::dropIfExists('animal_exam_species');
        Schema::dropIfExists('animal_exams');
    }
};
