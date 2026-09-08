<?php

namespace App\Modules\Vaccinations\Services;

use App\Modules\Patients\Models\AnimalSpecies;
use App\Modules\Patients\Models\Patient;
use App\Modules\Vaccinations\Models\AnimalVaccine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VaccineCatalogService
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function managementCatalog(?int $clinicId = null, ?int $speciesId = null): Collection
    {
        $effectiveClinicId = $this->tenant->clinicId() ?? $clinicId;

        return $this->visibleQuery($effectiveClinicId)
            ->with(['clinic', 'species' => fn ($query) => $query->orderBy('normalized_name')])
            ->when($speciesId, fn (Builder $query) => $this->applySpeciesCompatibility($query, $speciesId))
            ->where('active', true)
            ->orderBy('normalized_name')
            ->get();
    }

    public function formCatalog(?int $clinicId, ?int $includeVaccineId = null): Collection
    {
        return $this->visibleQuery($clinicId)
            ->with(['species' => fn ($query) => $query->orderBy('normalized_name')])
            ->where(function (Builder $query) use ($includeVaccineId): void {
                $query->where('active', true);

                if ($includeVaccineId) {
                    $query->orWhereKey($includeVaccineId);
                }
            })
            ->orderBy('normalized_name')
            ->get();
    }

    /** @param array<int, int|string> $speciesIds */
    public function createCustom(
        string $name,
        ?int $recommendedDoses,
        ?int $recommendedIntervalDays,
        array $speciesIds,
        int $clinicId
    ): AnimalVaccine {
        $normalized = $this->normalize($name);
        $existing = $this->visibleQuery($clinicId)
            ->where('normalized_name', $normalized)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Esta vacina já está disponível no catálogo.',
            ]);
        }

        $ids = $this->validateSpeciesIds($speciesIds, $clinicId);

        return DB::transaction(function () use ($clinicId, $name, $normalized, $recommendedDoses, $recommendedIntervalDays, $ids): AnimalVaccine {
            $vaccine = AnimalVaccine::query()->create([
                'clinic_id' => $clinicId,
                'name' => Str::of($name)->squish()->value(),
                'normalized_name' => $normalized,
                'recommended_doses' => $recommendedDoses,
                'recommended_interval_days' => $recommendedIntervalDays,
                'system' => false,
                'active' => true,
            ]);

            $vaccine->species()->sync($ids);

            return $vaccine;
        });
    }

    public function resolveForVaccination(?int $vaccineId, Patient $patient, int $clinicId): ?AnimalVaccine
    {
        if (! $vaccineId) {
            return null;
        }

        $vaccine = $this->visibleQuery($clinicId)
            ->with('species')
            ->whereKey($vaccineId)
            ->where('active', true)
            ->first();

        if (! $vaccine) {
            throw ValidationException::withMessages([
                'animal_vaccine_id' => 'A vacina selecionada não está disponível para esta clínica.',
            ]);
        }

        if ($vaccine->species->isEmpty()) {
            return $vaccine;
        }

        $speciesId = $this->patientSpeciesId($patient, $clinicId);

        if (! $speciesId || ! $vaccine->species->contains('id', $speciesId)) {
            throw ValidationException::withMessages([
                'animal_vaccine_id' => 'A vacina selecionada não está disponível para a espécie deste paciente.',
            ]);
        }

        return $vaccine;
    }

    /** @param array<int, int|string> $speciesIds
     *  @return array<int, int>
     */
    private function validateSpeciesIds(array $speciesIds, int $clinicId): array
    {
        $ids = collect($speciesIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $validCount = AnimalSpecies::query()
            ->whereIn('id', $ids)
            ->where('active', true)
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'species_ids' => 'Uma das espécies selecionadas não está disponível para esta clínica.',
            ]);
        }

        return $ids->all();
    }

    private function patientSpeciesId(Patient $patient, int $clinicId): ?int
    {
        if ($patient->animal_species_id) {
            return (int) $patient->animal_species_id;
        }

        $name = trim((string) $patient->species);

        if ($name === '') {
            return null;
        }

        return AnimalSpecies::query()
            ->where('normalized_name', $this->normalize($name))
            ->where(function (Builder $query) use ($clinicId): void {
                $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
            })
            ->value('id');
    }

    private function visibleQuery(?int $clinicId): Builder
    {
        return AnimalVaccine::query()
            ->when(
                $clinicId !== null,
                fn (Builder $query) => $query->where(function (Builder $visible) use ($clinicId): void {
                    $visible->whereNull('clinic_id')->orWhere('clinic_id', $clinicId);
                }),
                fn (Builder $query) => $query->whereNull('clinic_id')
            );
    }

    private function applySpeciesCompatibility(Builder $query, int $speciesId): Builder
    {
        return $query->where(function (Builder $compatible) use ($speciesId): void {
            $compatible
                ->whereDoesntHave('species')
                ->orWhereHas('species', fn (Builder $species) => $species->whereKey($speciesId));
        });
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
}
