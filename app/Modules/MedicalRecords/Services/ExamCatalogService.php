<?php

namespace App\Modules\MedicalRecords\Services;

use App\Modules\MedicalRecords\Models\AnimalExam;
use App\Modules\Patients\Models\AnimalSpecies;
use App\Modules\Patients\Models\Patient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamCatalogService
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function managementCatalog(?int $clinicId = null, ?int $speciesId = null): Collection
    {
        $effectiveClinicId = $this->tenant->clinicId() ?? $clinicId;

        return $this->visibleQuery($effectiveClinicId)
            ->with(['clinic', 'species' => fn ($query) => $query->orderBy('normalized_name')])
            ->when($speciesId, fn (Builder $query) => $this->applySpeciesCompatibility($query, $speciesId))
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('normalized_name')
            ->get();
    }

    public function formCatalog(?int $clinicId): Collection
    {
        return $this->visibleQuery($clinicId)
            ->with(['species' => fn ($query) => $query->orderBy('normalized_name')])
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('normalized_name')
            ->get();
    }

    /** @param array<int, int|string> $speciesIds */
    public function createCustom(string $name, ?string $category, array $speciesIds, int $clinicId): AnimalExam
    {
        $normalized = $this->normalize($name);
        $existing = $this->visibleQuery($clinicId)
            ->where('normalized_name', $normalized)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Este exame já está disponível no catálogo.',
            ]);
        }

        $ids = $this->validateSpeciesIds($speciesIds, $clinicId);

        return DB::transaction(function () use ($clinicId, $name, $normalized, $category, $ids): AnimalExam {
            $exam = AnimalExam::query()->create([
                'clinic_id' => $clinicId,
                'name' => Str::of($name)->squish()->value(),
                'normalized_name' => $normalized,
                'category' => Str::of((string) $category)->squish()->limit(80, '')->value() ?: null,
                'system' => false,
                'active' => true,
            ]);

            $exam->species()->sync($ids);

            return $exam;
        });
    }

    /** @param array<int, int|string> $examIds
     *  @return array<int, int>
     */
    public function resolveForRecord(array $examIds, Patient $patient, int $clinicId): array
    {
        $ids = collect($examIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $speciesId = $this->patientSpeciesId($patient, $clinicId);
        $validCount = $this->visibleQuery($clinicId)
            ->where('active', true)
            ->when($speciesId, fn (Builder $query) => $this->applySpeciesCompatibility($query, $speciesId))
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'exam_ids' => 'Um dos exames não está disponível para a espécie ou para esta clínica.',
            ]);
        }

        return $ids->all();
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
        return AnimalExam::query()
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
