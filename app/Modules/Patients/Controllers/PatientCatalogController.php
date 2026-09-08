<?php

namespace App\Modules\Patients\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Services\PatientTaxonomyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientCatalogController
{
    public function __construct(
        private readonly PatientTaxonomyService $taxonomy,
        private readonly TenantContext $tenant
    ) {}

    public function species(Request $request)
    {
        $clinicId = $this->selectedClinicId($request);

        return view('patients.catalog.species', [
            'speciesRows' => $this->taxonomy->managementSpecies($clinicId),
            'categories' => PatientTaxonomyService::CATEGORY_LABELS,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function specialties(Request $request)
    {
        $clinicId = $this->selectedClinicId($request);

        return view('patients.catalog.specialties', [
            'speciesRows' => $this->taxonomy->specialtySpecies($clinicId),
            'selectedSpeciesIds' => $this->taxonomy->selectedSpecialtyIds(),
            'categories' => PatientTaxonomyService::CATEGORY_LABELS,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function updateSpecialties(Request $request)
    {
        $validated = $request->validate([
            'species_ids' => ['nullable', 'array'],
            'species_ids.*' => ['integer'],
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);
        $clinicId = $this->selectedClinicId($request);

        $this->taxonomy->updateSpecialties($validated['species_ids'] ?? [], $clinicId);

        return redirect()
            ->route('patient-catalog.specialties', array_filter(['clinic_id' => $clinicId]))
            ->with('success', 'Espécies de atuação atualizadas. O catálogo já foi personalizado para você.');
    }

    public function storeSpecies(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', Rule::in(array_keys(PatientTaxonomyService::CATEGORY_LABELS))],
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);
        $clinicId = $this->targetClinicId($validated);

        $this->taxonomy->createCustomSpecies($validated['name'], $validated['category'], $clinicId);

        return redirect()
            ->route('patient-catalog.species', array_filter(['clinic_id' => $clinicId]))
            ->with('success', 'Espécie adicionada ao catálogo da clínica.');
    }

    public function breeds(Request $request)
    {
        $clinicId = $this->selectedClinicId($request);
        $speciesRows = $this->taxonomy->managementSpecies($clinicId);
        $selectedSpeciesId = (int) $request->query('species_id', $speciesRows->first()?->id);
        $selectedSpecies = $speciesRows->firstWhere('id', $selectedSpeciesId);

        if (! $selectedSpecies && $speciesRows->isNotEmpty()) {
            $selectedSpecies = $speciesRows->first();
            $selectedSpeciesId = (int) $selectedSpecies->id;
        }

        return view('patients.catalog.breeds', [
            'speciesRows' => $speciesRows,
            'selectedSpecies' => $selectedSpecies,
            'breedRows' => $selectedSpecies
                ? $this->taxonomy->managementBreeds($selectedSpeciesId, $clinicId)
                : collect(),
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function storeBreed(Request $request)
    {
        $validated = $request->validate([
            'animal_species_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);
        $clinicId = $this->targetClinicId($validated);

        $this->taxonomy->createCustomBreed(
            (int) $validated['animal_species_id'],
            $validated['name'],
            $clinicId
        );

        return redirect()
            ->route('patient-catalog.breeds', array_filter([
                'clinic_id' => $clinicId,
                'species_id' => $validated['animal_species_id'],
            ]))
            ->with('success', 'Raça ou variedade adicionada ao catálogo da clínica.');
    }

    public function coats(Request $request)
    {
        $clinicId = $this->selectedClinicId($request);
        $speciesRows = $this->taxonomy->managementSpecies($clinicId);
        $selectedSpeciesId = (int) $request->query('species_id', $speciesRows->first()?->id);
        $selectedSpecies = $speciesRows->firstWhere('id', $selectedSpeciesId);

        if (! $selectedSpecies && $speciesRows->isNotEmpty()) {
            $selectedSpecies = $speciesRows->first();
            $selectedSpeciesId = (int) $selectedSpecies->id;
        }

        return view('patients.catalog.coats', [
            'speciesRows' => $speciesRows,
            'selectedSpecies' => $selectedSpecies,
            'coatRows' => $selectedSpecies
                ? $this->taxonomy->managementCoats($selectedSpeciesId, $clinicId)
                : collect(),
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function storeCoat(Request $request)
    {
        $validated = $request->validate([
            'animal_species_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);
        $clinicId = $this->targetClinicId($validated);

        $this->taxonomy->createCustomCoat(
            (int) $validated['animal_species_id'],
            $validated['name'],
            $clinicId
        );

        return redirect()
            ->route('patient-catalog.coats', array_filter([
                'clinic_id' => $clinicId,
                'species_id' => $validated['animal_species_id'],
            ]))
            ->with('success', 'Pelagem ou padrão adicionado ao catálogo da clínica.');
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
