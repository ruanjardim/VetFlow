<?php

namespace App\Modules\Patients\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Patients\Requests\StorePatientRequest;
use App\Modules\Patients\Requests\UpdatePatientRequest;
use App\Modules\Patients\Services\PatientClinicalProfileService;
use App\Modules\Patients\Services\PatientService;
use App\Modules\Patients\Services\PatientTaxonomyService;
use App\Modules\Tutors\Models\Tutor;

class PatientController extends BaseCrudController
{
    public function __construct(
        PatientService $service,
        private readonly PatientTaxonomyService $taxonomy,
        private readonly PatientClinicalProfileService $profile
    ) {
        $this->service = $service;
        $this->viewPath = 'patients';
        $this->routeName = 'patients';
        $this->viewVariable = 'patients';
    }

    public function create()
    {
        return view('patients.create', $this->formData());
    }

    public function edit(int $id)
    {
        $patient = $this->service->findOrFail($id);

        return view('patients.edit', array_merge($this->formData($patient), [
            'item' => $patient,
        ]));
    }

    public function show(int $id)
    {
        $user = auth()->user();

        return view('patients.show', $this->profile->forPatient($id, [
            'appointments' => $user?->can('appointments.manage') ?? false,
            'medicalRecords' => $user?->can('medical-records.manage') ?? false,
            'vaccinations' => $user?->can('vaccinations.manage') ?? false,
            'hospitalizations' => $user?->can('hospitalizations.manage') ?? false,
        ]));
    }

    protected function storeRequest(): string
    {
        return StorePatientRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdatePatientRequest::class;
    }

    private function availableTutors()
    {
        return Tutor::query()
            ->orderBy('name')
            ->get();
    }

    private function formData(?object $patient = null): array
    {
        return [
            'tutors' => $this->availableTutors(),
            'speciesCatalog' => $this->taxonomy->formCatalog(
                $patient?->animal_species_id ? (int) $patient->animal_species_id : null
            ),
            'speciesCategories' => PatientTaxonomyService::CATEGORY_LABELS,
            'taxonomySelection' => $this->taxonomy->selectedIds($patient),
        ];
    }
}
