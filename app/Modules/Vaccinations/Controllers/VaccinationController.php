<?php

namespace App\Modules\Vaccinations\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use App\Modules\Vaccinations\Requests\StoreVaccinationRequest;
use App\Modules\Vaccinations\Requests\UpdateVaccinationRequest;
use App\Modules\Vaccinations\Services\VaccineCatalogService;
use App\Modules\Vaccinations\Services\VaccinationService;
use App\Support\Tenancy\TenantContext;

class VaccinationController extends BaseCrudController
{
    public function __construct(
        VaccinationService $service,
        private readonly VaccineCatalogService $vaccines,
        private readonly TenantContext $tenant
    ) {
        $this->service = $service;
        $this->viewPath = 'vaccinations';
        $this->routeName = 'vaccinations';
        $this->viewVariable = 'vaccinations';
    }

    public function create()
    {
        return view('vaccinations.create', array_merge($this->formData(), [
            'preselectedPatientId' => (int) request()->query('patient_id'),
        ]));
    }

    public function edit(int $id)
    {
        $vaccination = $this->service->findOrFail($id);

        return view('vaccinations.edit', array_merge($this->formData($vaccination->animal_vaccine_id, $vaccination->clinic_id), [
            'vaccination' => $vaccination,
        ]));
    }

    protected function storeRequest(): string
    {
        return StoreVaccinationRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateVaccinationRequest::class;
    }

    private function formData(?int $includeVaccineId = null, ?int $clinicId = null): array
    {
        return [
            'patients' => Patient::query()->orderBy('name')->get(),
            'medicalRecords' => MedicalRecord::query()->with('patient')->latest('examined_at')->get(),
            'vaccines' => $this->vaccines->formCatalog($this->tenant->clinicId() ?? $clinicId, $includeVaccineId),
        ];
    }
}
