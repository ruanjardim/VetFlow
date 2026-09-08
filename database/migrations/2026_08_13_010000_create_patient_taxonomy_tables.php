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
        Schema::create('animal_species', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->string('category', 40)->index();
            $table->boolean('system')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['clinic_id', 'normalized_name']);
        });

        Schema::create('animal_breeds', function (Blueprint $table) {
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

        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('animal_species_id')
                ->nullable()
                ->after('name')
                ->constrained('animal_species')
                ->nullOnDelete();
            $table->foreignId('animal_breed_id')
                ->nullable()
                ->after('species')
                ->constrained('animal_breeds')
                ->nullOnDelete();
        });

        $this->seedCatalog();
        $this->linkExistingPatients();
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('animal_breed_id');
            $table->dropConstrainedForeignId('animal_species_id');
        });

        Schema::dropIfExists('animal_breeds');
        Schema::dropIfExists('animal_species');
    }

    private function seedCatalog(): void
    {
        $catalog = [
            'Companhia' => [
                'Canino' => ['Sem raça definida', 'Akita', 'Beagle', 'Border Collie', 'Boston Terrier', 'Boxer', 'Bulldog Francês', 'Bulldog Inglês', 'Chihuahua', 'Chow Chow', 'Cocker Spaniel', 'Dachshund', 'Dálmata', 'Golden Retriever', 'Husky Siberiano', 'Labrador Retriever', 'Lhasa Apso', 'Maltês', 'Pastor Alemão', 'Pinscher', 'Pit Bull', 'Poodle', 'Pug', 'Rottweiler', 'Schnauzer', 'Shih-tzu', 'Spitz Alemão', 'Yorkshire Terrier'],
                'Felino' => ['Sem raça definida', 'Abissínio', 'Angorá', 'Bengal', 'British Shorthair', 'Maine Coon', 'Persa', 'Ragdoll', 'Sagrado da Birmânia', 'Savannah', 'Scottish Fold', 'Siamês', 'Sphynx'],
                'Coelho' => ['Sem raça definida', 'Angorá', 'Fuzzy Lop', 'Gigante de Flandres', 'Holland Lop', 'Hotot', 'Lionhead', 'Mini Lop', 'Mini Rex', 'Nova Zelândia', 'Rex'],
                'Furão' => ['Sem raça definida'],
                'Porquinho-da-índia' => ['Sem raça definida', 'Abissínio', 'Inglês', 'Peruano', 'Sheltie', 'Skinny', 'Texel'],
                'Hamster' => ['Sírio', 'Anão Russo', 'Roborovski', 'Chinês'],
                'Chinchila' => ['Padrão', 'Bege', 'Ebony', 'Mosaico', 'Violeta'],
                'Rato doméstico' => ['Sem raça definida'],
                'Camundongo doméstico' => ['Sem raça definida'],
                'Gerbil' => ['Sem raça definida'],
                'Degú' => ['Sem raça definida'],
                'Ouriço pigmeu africano' => ['Sem raça definida'],
                'Petauro-do-açúcar' => ['Sem raça definida'],
                'Roedor' => [],
                'Exótico' => [],
            ],
            'Aves' => [
                'Calopsita' => ['Cinza silvestre', 'Arlequim', 'Cara-branca', 'Canela', 'Lutino', 'Pérola'],
                'Periquito-australiano' => ['Sem raça definida'],
                'Agapornis' => ['Sem raça definida'],
                'Canário' => ['Sem raça definida'],
                'Papagaio' => ['Sem raça definida'],
                'Arara' => ['Sem raça definida'],
                'Cacatua' => ['Sem raça definida'],
                'Galinha' => ['Sem raça definida', 'Brahma', 'Índio Gigante', 'Orpington', 'Sedosa'],
                'Pato' => ['Sem raça definida', 'Muscovy', 'Pekin'],
                'Ave' => [],
                'Ave silvestre' => [],
            ],
            'Répteis e anfíbios' => [
                'Jabuti' => [],
                'Tartaruga' => [],
                'Cágado' => [],
                'Iguana' => [],
                'Gecko' => [],
                'Camaleão' => [],
                'Serpente' => [],
                'Dragão-barbudo' => [],
                'Teiú' => [],
                'Réptil' => [],
                'Axolote' => [],
                'Salamandra' => [],
                'Rã' => [],
                'Sapo' => [],
                'Anfíbio' => [],
                'Réptil silvestre' => [],
            ],
            'Aquáticos' => [
                'Peixe ornamental' => [],
                'Peixe de produção' => [],
                'Peixe' => [],
            ],
            'Grandes animais' => [
                'Equino' => ['Sem raça definida', 'Árabe', 'Campolina', 'Crioulo', 'Friesian', 'Lusitano', 'Mangalarga', 'Mangalarga Marchador', 'Paint Horse', 'Pampa', 'Puro Sangue Inglês', 'Quarto de Milha'],
                'Bovino' => ['Sem raça definida', 'Angus', 'Brahman', 'Gir', 'Girolando', 'Guzerá', 'Holandês', 'Jersey', 'Nelore', 'Simental'],
                'Bubalino' => ['Sem raça definida', 'Jafarabadi', 'Mediterrâneo', 'Murrah'],
                'Caprino' => ['Sem raça definida', 'Anglo-Nubiana', 'Boer', 'Moxotó', 'Saanen', 'Toggenburg'],
                'Ovino' => ['Sem raça definida', 'Dorper', 'Hampshire Down', 'Santa Inês', 'Suffolk', 'Texel'],
                'Suíno' => ['Sem raça definida', 'Duroc', 'Landrace', 'Large White', 'Pietrain'],
                'Lhama' => ['Sem raça definida'],
                'Alpaca' => ['Huacaya', 'Suri'],
            ],
            'Silvestres e outros' => [
                'Mamífero silvestre' => [],
                'Marsupial' => [],
                'Primata' => [],
                'Quelônio' => [],
                'Animal silvestre' => [],
                'Silvestre' => [],
            ],
        ];

        $now = now();
        $speciesInserts = [];

        foreach ($catalog as $category => $speciesRows) {
            foreach ($speciesRows as $speciesName => $breeds) {
                $speciesInserts[] = [
                    'clinic_id' => null,
                    'name' => $speciesName,
                    'normalized_name' => $this->normalize($speciesName),
                    'category' => $category,
                    'system' => true,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('animal_species')->insert($speciesInserts);

        $speciesIds = DB::table('animal_species')
            ->where('system', true)
            ->pluck('id', 'normalized_name');
        $breedInserts = [];

        foreach ($catalog as $speciesRows) {
            foreach ($speciesRows as $speciesName => $breeds) {
                $speciesId = $speciesIds[$this->normalize($speciesName)];

                foreach ($breeds as $breedName) {
                    $breedInserts[] = [
                        'animal_species_id' => $speciesId,
                        'clinic_id' => null,
                        'name' => $breedName,
                        'normalized_name' => $this->normalize($breedName),
                        'system' => true,
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($breedInserts, 100) as $breedChunk) {
            DB::table('animal_breeds')->insert($breedChunk);
        }
    }

    private function linkExistingPatients(): void
    {
        $species = DB::table('animal_species')->get()->keyBy('normalized_name');

        DB::table('patients')
            ->whereNotNull('species')
            ->orderBy('id')
            ->each(function ($patient) use ($species): void {
                $speciesRow = $species->get($this->normalize((string) $patient->species));

                if (! $speciesRow) {
                    return;
                }

                $breedId = null;

                if ($patient->breed) {
                    $breedId = DB::table('animal_breeds')
                        ->where('animal_species_id', $speciesRow->id)
                        ->where('normalized_name', $this->normalize((string) $patient->breed))
                        ->value('id');
                }

                DB::table('patients')->where('id', $patient->id)->update([
                    'animal_species_id' => $speciesRow->id,
                    'animal_breed_id' => $breedId,
                ]);
            });
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
};
