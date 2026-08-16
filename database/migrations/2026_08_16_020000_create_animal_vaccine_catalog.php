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
        Schema::create('animal_vaccines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->unsignedTinyInteger('recommended_doses')->nullable();
            $table->unsignedSmallInteger('recommended_interval_days')->nullable();
            $table->boolean('system')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['clinic_id', 'normalized_name']);
        });

        Schema::create('animal_vaccine_species', function (Blueprint $table): void {
            $table->foreignId('animal_vaccine_id')->constrained('animal_vaccines')->cascadeOnDelete();
            $table->foreignId('animal_species_id')->constrained('animal_species')->cascadeOnDelete();

            $table->unique(['animal_vaccine_id', 'animal_species_id'], 'vaccine_species_unique');
        });

        Schema::table('vaccinations', function (Blueprint $table): void {
            $table->foreignId('animal_vaccine_id')->nullable()->constrained('animal_vaccines')->nullOnDelete();
        });

        $this->seedCatalog();
    }

    public function down(): void
    {
        Schema::table('vaccinations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('animal_vaccine_id');
        });

        Schema::dropIfExists('animal_vaccine_species');
        Schema::dropIfExists('animal_vaccines');
    }

    private function seedCatalog(): void
    {
        $catalog = require database_path('data/animal_vaccine_catalog.php');
        $species = DB::table('animal_species')
            ->whereNull('clinic_id')
            ->get(['id', 'normalized_name'])
            ->keyBy('normalized_name');
        $now = now();

        foreach ($catalog as $entry) {
            $vaccineId = DB::table('animal_vaccines')->insertGetId([
                'clinic_id' => null,
                'name' => $entry['name'],
                'normalized_name' => $this->normalize($entry['name']),
                'recommended_doses' => null,
                'recommended_interval_days' => null,
                'system' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $links = collect($entry['species'])
                ->map(fn (string $name) => $species->get($this->normalize($name)))
                ->filter()
                ->map(fn (object $row): array => [
                    'animal_vaccine_id' => $vaccineId,
                    'animal_species_id' => $row->id,
                ])
                ->values()
                ->all();

            if ($links !== []) {
                DB::table('animal_vaccine_species')->insert($links);
            }
        }
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
};
