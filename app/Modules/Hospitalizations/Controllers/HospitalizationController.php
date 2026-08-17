<?php

namespace App\Modules\Hospitalizations\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Hospitalizations\Requests\StoreHospitalizationRequest;
use App\Modules\Hospitalizations\Requests\UpdateHospitalizationRequest;
use App\Modules\Hospitalizations\Services\HospitalizationService;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;

class HospitalizationController extends BaseCrudController
{
    public function __construct(HospitalizationService $service)
    {
        $this->service = $service;
        $this->viewPath = 'hospitalizations';
        $this->routeName = 'hospitalizations';
        $this->viewVariable = 'hospitalizations';
    }

    public function create()
    {
        return view('hospitalizations.create', array_merge($this->formData(), [
            'preselectedPatientId' => (int) request()->query('patient_id'),
        ]));
    }

    public function edit(int $id)
    {
        $hospitalization = $this->service->findOrFail($id);

        return view('hospitalizations.edit', array_merge($this->formData(), [
            'hospitalization' => $hospitalization,
        ]));
    }

    protected function storeRequest(): string
    {
        return StoreHospitalizationRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateHospitalizationRequest::class;
    }

    private function formData(): array
    {
        return [
            'patients' => Patient::query()->with('tutor')->orderBy('name')->get(),
            'medicalRecords' => MedicalRecord::query()->with('patient')->latest('examined_at')->get(),
        ];
    }
}
