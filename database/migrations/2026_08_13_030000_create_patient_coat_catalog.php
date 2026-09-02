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
        Schema::create('animal_coats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('animal_species_id')->constrained('animal_species')->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->boolean('system')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['animal_species_id', 'clinic_id', 'normalized_name']);
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->foreignId('animal_coat_id')
                ->nullable()
                ->after('animal_breed_id')
                ->constrained('animal_coats')
                ->nullOnDelete();
            $table->string('coat')->nullable()->after('breed');
        });

        $this->seedCatalog();
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('animal_coat_id');
            $table->dropColumn('coat');
        });

        Schema::dropIfExists('animal_coats');
    }

    private function seedCatalog(): void
    {
        /** @var array<string, array<int, string>> $catalog */
        $catalog = require database_path('data/patient_coat_catalog.php');
        $now = now();
        $rows = [];

        foreach ($catalog as $speciesName => $coats) {
            $speciesId = DB::table('animal_species')
                ->whereNull('clinic_id')
                ->where('normalized_name', $this->normalize($speciesName))
                ->value('id');

            if (! $speciesId) {
                continue;
            }

            foreach ($coats as $coatName) {
                $rows[] = [
                    'animal_species_id' => $speciesId,
                    'clinic_id' => null,
                    'name' => $coatName,
                    'normalized_name' => $this->normalize($coatName),
                    'system' => true,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('animal_coats')->insert($chunk);
        }
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
};
