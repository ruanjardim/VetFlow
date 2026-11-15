<?php

namespace App\Modules\Vaccinations\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Services\PatientTaxonomyService;
use App\Modules\Vaccinations\Services\VaccineCatalogService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VaccineCatalogController
{
    public function __construct(
        private readonly VaccineCatalogService $vaccines,
        private readonly PatientTaxonomyService $taxonomy,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request)
    {
        $clinicId = $this->selectedClinicId($request);
        $speciesRows = $this->taxonomy->managementSpecies($clinicId);
        $selectedSpeciesId = (int) $request->query('species_id');
        $selectedSpecies = $selectedSpeciesId > 0
            ? $speciesRows->firstWhere('id', $selectedSpeciesId)
            : null;

        if ($selectedSpeciesId > 0 && ! $selectedSpecies) {
            $selectedSpeciesId = 0;
        }

        return view('vaccinations.catalog', [
            'vaccineRows' => $this->vaccines->managementCatalog($clinicId, $selectedSpeciesId ?: null),
            'speciesRows' => $speciesRows,
            'selectedSpecies' => $selectedSpecies,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'recommended_doses' => ['nullable', 'integer', 'min:1', 'max:99'],
            'recommended_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'species_ids' => ['nullable', 'array'],
            'species_ids.*' => ['integer', 'distinct'],
            'return_species_id' => ['nullable', 'integer'],
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);
        $clinicId = $this->targetClinicId($validated);

        $this->vaccines->createCustom(
            $validated['name'],
            $validated['recommended_doses'] ?? null,
            $validated['recommended_interval_days'] ?? null,
            $validated['species_ids'] ?? [],
            $clinicId
        );

        return redirect()
            ->route('vaccine-catalog.index', array_filter([
                'clinic_id' => $clinicId,
                'species_id' => $validated['return_species_id'] ?? null,
            ]))
            ->with('success', 'Vacina adicionada ao catálogo da clínica.');
    }

    private function selectedClinicId(Request $request): ?int
    {
        if (! $this->tenant->isGlobal()) {
            return $this->tenant->clinicId();
        }

        $clinicId = (int) $request->query('clinic_id');

        return $clinicId > 0 && Clinic::query()->whereKey($clinicId)->where('active', true)->exists()
            ? $clinicId
            : null;
    }

    private function targetClinicId(array $validated): int
    {
        if (! $this->tenant->isGlobal()) {
            return (int) $this->tenant->clinicId();
        }

        $clinicId = (int) ($validated['clinic_id'] ?? 0);

        if ($clinicId <= 0) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Selecione a clínica que receberá este cadastro.',
            ]);
        }

        return $clinicId;
    }

    private function availableClinics()
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }
}
