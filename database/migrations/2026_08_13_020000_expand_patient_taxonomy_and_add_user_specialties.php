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
        Schema::table('animal_breeds', function (Blueprint $table): void {
            $table->string('reference_source', 80)->nullable()->after('normalized_name');
        });

        Schema::create('user_animal_species', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('animal_species_id')->constrained('animal_species')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'animal_species_id']);
        });

        $this->expandCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_animal_species');

        Schema::table('animal_breeds', function (Blueprint $table): void {
            $table->dropColumn('reference_source');
        });
    }

    private function expandCatalog(): void
    {
        /** @var array<string, array{source: string, breeds: array<int, string>}> $catalog */
        $catalog = require database_path('data/patient_taxonomy_catalog_v2.php');
        $aliases = $this->aliases();
        $now = now();
        $inserts = [];

        foreach ($catalog as $speciesName => $definition) {
            $speciesId = DB::table('animal_species')
                ->whereNull('clinic_id')
                ->where('normalized_name', $this->normalize($speciesName))
                ->value('id');

            if (! $speciesId) {
                continue;
            }

            DB::table('animal_breeds')
                ->where('animal_species_id', $speciesId)
                ->whereNull('clinic_id')
                ->update(['reference_source' => $definition['source']]);

            $existing = DB::table('animal_breeds')
                ->where('animal_species_id', $speciesId)
                ->whereNull('clinic_id')
                ->pluck('id', 'normalized_name');

            foreach ($definition['breeds'] as $breedName) {
                $normalized = $this->normalize($breedName);
                $canonicalNormalized = $aliases[$speciesName][$normalized] ?? $normalized;

                if ($existing->has($canonicalNormalized)) {
                    continue;
                }

                $inserts[] = [
                    'animal_species_id' => $speciesId,
                    'clinic_id' => null,
                    'name' => $breedName,
                    'normalized_name' => $canonicalNormalized,
                    'reference_source' => $definition['source'],
                    'system' => true,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $existing->put($canonicalNormalized, true);
            }
        }

        foreach (array_chunk($inserts, 100) as $chunk) {
            DB::table('animal_breeds')->insert($chunk);
        }
    }

    /** @return array<string, array<string, string>> */
    private function aliases(): array
    {
        return [
            'Canino' => [
                'bulldog' => 'bulldog ingles',
                'dalmatian' => 'dalmata',
                'french bulldog' => 'bulldog frances',
                'german shepherd dog' => 'pastor alemao',
                'german spitz' => 'spitz alemao',
                'maltese' => 'maltes',
                'miniature pinscher' => 'pinscher',
                'siberian husky' => 'husky siberiano',
            ],
            'Felino' => [
                'abyssinian' => 'abissinio',
                'birman' => 'sagrado da birmania',
                'persian' => 'persa',
                'siamese' => 'siames',
                'turkish angora' => 'angora',
            ],
            'Equino' => [
                'american paint horse' => 'paint horse',
                'american quarter horse' => 'quarto de milha',
            ],
            'Bovino' => [
                'brown swiss (pardo-suico)' => 'pardo-suico',
            ],
        ];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
};
