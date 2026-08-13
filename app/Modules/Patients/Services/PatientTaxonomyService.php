<?php

namespace App\Modules\Patients\Services;

use App\Modules\Patients\Models\AnimalBreed;
use App\Modules\Patients\Models\AnimalCoat;
use App\Modules\Patients\Models\AnimalSpecies;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientTaxonomyService
{
    public const CATEGORY_LABELS = [
        'Companhia' => 'Companhia',
        'Aves' => 'Aves',
        'Répteis e anfíbios' => 'Répteis e anfíbios',
        'Aquáticos' => 'Aquáticos',
        'Grandes animais' => 'Grandes animais',
        'Silvestres e outros' => 'Silvestres e outros',
    ];

    public function __construct(private readonly TenantContext $tenant) {}

    public function formCatalog(?int $includeSpeciesId = null): Collection
    {
        $clinicId = $this->tenant->clinicId();

        $query = AnimalSpecies::query();

        if ($clinicId !== null) {
            $query->where(function (Builder $visible) use ($clinicId): void {
                $visible->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            });
        }

        $this->applyCurrentUserPreferences($query, $includeSpeciesId);

        return $query
            ->with([
                'clinic',
                'breeds' => fn ($query) => $this->applyCatalogVisibility($query, $clinicId),
                'coats' => fn ($query) => $this->applyCatalogVisibility($query, $clinicId),
            ])
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function managementSpecies(?int $clinicId = null, bool $respectPreferences = true): Collection
    {
        $effectiveClinicId = $this->tenant->clinicId() ?? $clinicId;

        $query = $this->visibleSpeciesQuery($effectiveClinicId);

        if ($respectPreferences) {
            $this->applyCurrentUserPreferences($query);
        }

        return $query
            ->with('clinic')
            ->withCount([
                'breeds' => fn ($query) => $this->applyCatalogVisibility($query, $effectiveClinicId, false),
                'coats' => fn ($query) => $this->applyCatalogVisibility($query, $effectiveClinicId, false),
            ])
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function specialtySpecies(?int $clinicId = null): Collection
    {
        return $this->managementSpecies($clinicId, false);
    }

    /** @return array<int, int> */
    public function selectedSpecialtyIds(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return $user->animalSpeciesPreferences()
            ->pluck('animal_species.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @param array<int, int|string> $speciesIds */
    public function updateSpecialties(array $speciesIds, ?int $clinicId = null): void
    {
        $user = Auth::user();

        if (! $user) {
            throw ValidationException::withMessages([
                'species_ids' => 'Usuário não autenticado.',
            ]);
        }

        $ids = collect($speciesIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $effectiveClinicId = $this->tenant->clinicId() ?? $clinicId;
        $visibleCount = $this->visibleSpeciesQuery($effectiveClinicId)
            ->whereIn('id', $ids)
            ->where('active', true)
            ->count();

        if ($visibleCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'species_ids' => 'Uma das espécies selecionadas não está disponível para este perfil.',
            ]);
        }

        $user->animalSpeciesPreferences()->sync($ids->all());
    }

    public function managementBreeds(int $speciesId, ?int $clinicId = null): Collection
    {
        $effectiveClinicId = $this->tenant->clinicId() ?? $clinicId;
        $species = $this->findVisibleSpecies($speciesId, $effectiveClinicId);
        $query = AnimalBreed::query()
            ->with('clinic')
            ->where('animal_species_id', $species->id);

        $this->applyCatalogVisibility($query, $effectiveClinicId, false);

        return $query
            ->orderBy('name')
            ->get();
    }

    public function managementCoats(int $speciesId, ?int $clinicId = null): Collection
    {
        $effectiveClinicId = $this->tenant->clinicId() ?? $clinicId;
        $species = $this->findVisibleSpecies($speciesId, $effectiveClinicId);
        $query = AnimalCoat::query()
            ->with('clinic')
            ->where('animal_species_id', $species->id);

        $this->applyCatalogVisibility($query, $effectiveClinicId, false);

        return $query
            ->orderBy('name')
            ->get();
    }

    public function applyToPatientData(array $data, int $clinicId): array
    {
        $species = $this->resolveSpecies($data, $clinicId);
        $breed = $this->resolveBreed($data, $clinicId, $species);
        $coat = $this->resolveCoat($data, $clinicId, $species);

        $data['animal_species_id'] = $species?->id;
        $data['species'] = $species?->name;
        $data['animal_breed_id'] = $breed?->id;
        $data['breed'] = $breed?->name;
        $data['animal_coat_id'] = $coat?->id;
        $data['coat'] = $coat?->name;

        unset(
            $data['species_choice'],
            $data['new_species'],
            $data['breed_choice'],
            $data['new_breed'],
            $data['coat_choice'],
            $data['new_coat']
        );

        return $data;
    }

    public function selectedIds(?object $patient): array
    {
        if (! $patient) {
            return [
                'species' => null,
                'breed' => null,
                'coat' => null,
                'new_species' => null,
                'new_breed' => null,
                'new_coat' => null,
            ];
        }

        $clinicId = $patient->clinic_id !== null ? (int) $patient->clinic_id : $this->tenant->clinicId();
        $speciesId = $patient->animal_species_id;
        $breedId = $patient->animal_breed_id;
        $coatId = $patient->animal_coat_id;

        if (! $speciesId && $patient->species) {
            $speciesId = $this->visibleSpeciesQuery($clinicId)
                ->where('normalized_name', $this->normalize($patient->species))
                ->value('id');
        }

        if (! $breedId && $speciesId && $patient->breed) {
            $breedId = AnimalBreed::query()
                ->where('animal_species_id', $speciesId)
                ->where('normalized_name', $this->normalize($patient->breed))
                ->when($clinicId !== null, function (Builder $query) use ($clinicId): void {
                    $query->where(function (Builder $visible) use ($clinicId): void {
                        $visible->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
                    });
                })
                ->value('id');
        }

        if (! $coatId && $speciesId && $patient->coat) {
            $coatId = AnimalCoat::query()
                ->where('animal_species_id', $speciesId)
                ->where('normalized_name', $this->normalize($patient->coat))
                ->when($clinicId !== null, function (Builder $query) use ($clinicId): void {
                    $query->where(function (Builder $visible) use ($clinicId): void {
                        $visible->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
                    });
                })
                ->value('id');
        }

        return [
            'species' => $speciesId ?: ($patient->species ? 'other' : null),
            'breed' => $breedId ?: ($patient->breed ? 'other' : null),
            'coat' => $coatId ?: ($patient->coat ? 'other' : null),
            'new_species' => $speciesId ? null : $patient->species,
            'new_breed' => $breedId ? null : $patient->breed,
            'new_coat' => $coatId ? null : $patient->coat,
        ];
    }

    public function createCustomSpecies(string $name, string $category, int $clinicId): AnimalSpecies
    {
        $normalized = $this->normalize($name);
        $existing = $this->visibleSpeciesQuery($clinicId)
            ->where('normalized_name', $normalized)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Esta espécie já está disponível no catálogo.',
            ]);
        }

        $species = AnimalSpecies::query()->create([
            'clinic_id' => $clinicId,
            'name' => Str::of($name)->squish()->value(),
            'normalized_name' => $normalized,
            'category' => array_key_exists($category, self::CATEGORY_LABELS)
                ? $category
                : 'Silvestres e outros',
            'system' => false,
            'active' => true,
        ]);

        Auth::user()?->animalSpeciesPreferences()->syncWithoutDetaching([$species->id]);

        return $species;
    }

    public function createCustomBreed(int $speciesId, string $name, int $clinicId): AnimalBreed
    {
        $species = $this->findVisibleSpecies($speciesId, $clinicId);

        if ($species->clinic_id !== null && (int) $species->clinic_id !== $clinicId) {
            throw ValidationException::withMessages([
                'animal_species_id' => 'A espécie selecionada não pertence a esta clínica.',
            ]);
        }

        $normalized = $this->normalize($name);
        $existing = AnimalBreed::query()
            ->where('animal_species_id', $species->id)
            ->where('normalized_name', $normalized)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Esta raça ou variedade já está disponível para a espécie.',
            ]);
        }

        return AnimalBreed::query()->create([
            'animal_species_id' => $species->id,
            'clinic_id' => $clinicId,
            'name' => Str::of($name)->squish()->value(),
            'normalized_name' => $normalized,
            'system' => false,
            'active' => true,
        ]);
    }

    public function createCustomCoat(int $speciesId, string $name, int $clinicId): AnimalCoat
    {
        $species = $this->findVisibleSpecies($speciesId, $clinicId);

        if ($species->clinic_id !== null && (int) $species->clinic_id !== $clinicId) {
            throw ValidationException::withMessages([
                'animal_species_id' => 'A espécie selecionada não pertence a esta clínica.',
            ]);
        }

        $normalized = $this->normalize($name);
        $existing = AnimalCoat::query()
            ->where('animal_species_id', $species->id)
            ->where('normalized_name', $normalized)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Esta pelagem ou padrão já está disponível para a espécie.',
            ]);
        }

        return AnimalCoat::query()->create([
            'animal_species_id' => $species->id,
            'clinic_id' => $clinicId,
            'name' => Str::of($name)->squish()->value(),
            'normalized_name' => $normalized,
            'system' => false,
            'active' => true,
        ]);
    }

    private function resolveSpecies(array $data, int $clinicId): ?AnimalSpecies
    {
        $choice = (string) ($data['species_choice'] ?? '');
        $newName = trim((string) ($data['new_species'] ?? ''));
        $legacyName = trim((string) ($data['species'] ?? ''));

        if ($choice === 'other' || $newName !== '') {
            if ($newName === '') {
                throw ValidationException::withMessages([
                    'new_species' => 'Informe o nome da nova espécie.',
                ]);
            }

            return $this->findOrCreateSpecies($newName, $clinicId);
        }

        if ($choice !== '') {
            if (! ctype_digit($choice)) {
                throw ValidationException::withMessages([
                    'species_choice' => 'Selecione uma espécie válida.',
                ]);
            }

            return $this->findVisibleSpecies((int) $choice, $clinicId);
        }

        return $legacyName !== '' ? $this->findOrCreateSpecies($legacyName, $clinicId) : null;
    }

    private function resolveBreed(array $data, int $clinicId, ?AnimalSpecies $species): ?AnimalBreed
    {
        $choice = (string) ($data['breed_choice'] ?? '');
        $newName = trim((string) ($data['new_breed'] ?? ''));
        $legacyName = trim((string) ($data['breed'] ?? ''));

        if ($choice === '' && $newName === '' && $legacyName === '') {
            return null;
        }

        if (! $species) {
            throw ValidationException::withMessages([
                'breed_choice' => 'Selecione a espécie antes de informar a raça ou variedade.',
            ]);
        }

        if ($choice === 'other' || $newName !== '') {
            if ($newName === '') {
                throw ValidationException::withMessages([
                    'new_breed' => 'Informe o nome da nova raça ou variedade.',
                ]);
            }

            return $this->findOrCreateBreed($species, $newName, $clinicId);
        }

        if ($choice !== '') {
            if (! ctype_digit($choice)) {
                throw ValidationException::withMessages([
                    'breed_choice' => 'Selecione uma raça ou variedade válida.',
                ]);
            }

            return $this->findVisibleBreed((int) $choice, $species, $clinicId);
        }

        return $legacyName !== '' ? $this->findOrCreateBreed($species, $legacyName, $clinicId) : null;
    }

    private function resolveCoat(array $data, int $clinicId, ?AnimalSpecies $species): ?AnimalCoat
    {
        $choice = (string) ($data['coat_choice'] ?? '');
        $newName = trim((string) ($data['new_coat'] ?? ''));
        $legacyName = trim((string) ($data['coat'] ?? ''));

        if ($choice === '' && $newName === '' && $legacyName === '') {
            return null;
        }

        if (! $species) {
            throw ValidationException::withMessages([
                'coat_choice' => 'Selecione a espécie antes de informar a pelagem ou padrão.',
            ]);
        }

        if ($choice === 'other' || $newName !== '') {
            if ($newName === '') {
                throw ValidationException::withMessages([
                    'new_coat' => 'Informe o nome da nova pelagem ou padrão.',
                ]);
            }

            return $this->findOrCreateCoat($species, $newName, $clinicId);
        }

        if ($choice !== '') {
            if (! ctype_digit($choice)) {
                throw ValidationException::withMessages([
                    'coat_choice' => 'Selecione uma pelagem ou padrão válido.',
                ]);
            }

            return $this->findVisibleCoat((int) $choice, $species, $clinicId);
        }

        return $legacyName !== '' ? $this->findOrCreateCoat($species, $legacyName, $clinicId) : null;
    }

    private function findOrCreateSpecies(string $name, int $clinicId): AnimalSpecies
    {
        $normalized = $this->normalize($name);
        $existing = $this->visibleSpeciesQuery($clinicId)
            ->where('normalized_name', $normalized)
            ->first();

        return $existing ?: $this->createCustomSpecies($name, 'Silvestres e outros', $clinicId);
    }

    private function findOrCreateBreed(AnimalSpecies $species, string $name, int $clinicId): AnimalBreed
    {
        $normalized = $this->normalize($name);
        $existing = AnimalBreed::query()
            ->where('animal_species_id', $species->id)
            ->where('normalized_name', $normalized)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->first();

        return $existing ?: $this->createCustomBreed($species->id, $name, $clinicId);
    }

    private function findOrCreateCoat(AnimalSpecies $species, string $name, int $clinicId): AnimalCoat
    {
        $normalized = $this->normalize($name);
        $existing = AnimalCoat::query()
            ->where('animal_species_id', $species->id)
            ->where('normalized_name', $normalized)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->first();

        return $existing ?: $this->createCustomCoat($species->id, $name, $clinicId);
    }

    private function findVisibleSpecies(int $id, ?int $clinicId): AnimalSpecies
    {
        $species = $this->visibleSpeciesQuery($clinicId)->whereKey($id)->where('active', true)->first();

        if (! $species) {
            throw ValidationException::withMessages([
                'species_choice' => 'A espécie selecionada não está disponível para esta clínica.',
            ]);
        }

        return $species;
    }

    private function findVisibleBreed(int $id, AnimalSpecies $species, int $clinicId): AnimalBreed
    {
        $breed = AnimalBreed::query()
            ->whereKey($id)
            ->where('animal_species_id', $species->id)
            ->where('active', true)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->first();

        if (! $breed) {
            throw ValidationException::withMessages([
                'breed_choice' => 'A raça ou variedade selecionada não pertence à espécie e à clínica informadas.',
            ]);
        }

        return $breed;
    }

    private function findVisibleCoat(int $id, AnimalSpecies $species, int $clinicId): AnimalCoat
    {
        $coat = AnimalCoat::query()
            ->whereKey($id)
            ->where('animal_species_id', $species->id)
            ->where('active', true)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->first();

        if (! $coat) {
            throw ValidationException::withMessages([
                'coat_choice' => 'A pelagem ou padrão selecionado não pertence à espécie e à clínica informadas.',
            ]);
        }

        return $coat;
    }

    private function visibleSpeciesQuery(?int $clinicId): Builder
    {
        return AnimalSpecies::query()
            ->when(
                $clinicId !== null,
                function (Builder $query) use ($clinicId): void {
                    $query->where(function (Builder $visible) use ($clinicId): void {
                        $visible->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
                    });
                },
                fn (Builder $query) => $query->whereNull('clinic_id')
            );
    }

    private function applyCurrentUserPreferences(Builder $query, ?int $includeSpeciesId = null): void
    {
        $preferenceIds = $this->selectedSpecialtyIds();

        if ($preferenceIds === []) {
            return;
        }

        if ($includeSpeciesId) {
            $preferenceIds[] = $includeSpeciesId;
        }

        $query->whereIn('animal_species.id', array_values(array_unique($preferenceIds)));
    }

    private function applyCatalogVisibility($query, ?int $clinicId, bool $onlyActive = true): void
    {
        if ($onlyActive) {
            $query->where('active', true);
        }

        if ($clinicId !== null) {
            $query->where(function (Builder $visible) use ($clinicId): void {
                $visible->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            });

            return;
        }

        $query->whereNull('clinic_id');
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
}
