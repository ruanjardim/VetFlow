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
        Schema::create('animal_pathologies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->boolean('system')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['clinic_id', 'normalized_name']);
        });

        Schema::create('animal_pathology_species', function (Blueprint $table): void {
            $table->foreignId('animal_pathology_id')->constrained('animal_pathologies')->cascadeOnDelete();
            $table->foreignId('animal_species_id')->constrained('animal_species')->cascadeOnDelete();

            $table->unique(['animal_pathology_id', 'animal_species_id'], 'pathology_species_unique');
        });

        Schema::create('medical_record_pathology', function (Blueprint $table): void {
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('animal_pathology_id')->constrained('animal_pathologies')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['medical_record_id', 'animal_pathology_id'], 'medical_record_pathology_unique');
        });

        $this->seedCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_pathology');
        Schema::dropIfExists('animal_pathology_species');
        Schema::dropIfExists('animal_pathologies');
    }

    private function seedCatalog(): void
    {
        /** @var array<int, array{name: string, species: array<int, string>}> $catalog */
        $catalog = require database_path('data/animal_pathology_catalog.php');
        $species = DB::table('animal_species')
            ->whereNull('clinic_id')
            ->get(['id', 'normalized_name'])
            ->keyBy('normalized_name');
        $now = now();

        foreach ($catalog as $entry) {
            $normalizedName = $this->normalize($entry['name']);
            $pathologyId = DB::table('animal_pathologies')->insertGetId([
                'clinic_id' => null,
                'name' => $entry['name'],
                'normalized_name' => $normalizedName,
                'system' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $links = collect($entry['species'])
                ->map(fn (string $name) => $species->get($this->normalize($name)))
                ->filter()
                ->map(fn (object $row): array => [
                    'animal_pathology_id' => $pathologyId,
                    'animal_species_id' => $row->id,
                ])
                ->values()
                ->all();

            if ($links !== []) {
                DB::table('animal_pathology_species')->insert($links);
            }
        }
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
};
