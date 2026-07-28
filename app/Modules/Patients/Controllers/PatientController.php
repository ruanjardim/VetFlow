<?php

namespace App\Modules\Patients\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Patients\Requests\StorePatientRequest;
use App\Modules\Patients\Requests\UpdatePatientRequest;
use App\Modules\Patients\Services\PatientService;
use App\Modules\Tutors\Models\Tutor;

class PatientController extends BaseCrudController
{
    public function __construct(PatientService $service)
    {
        $this->service = $service;
        $this->viewPath = 'patients';
        $this->routeName = 'patients';
        $this->viewVariable = 'patients';
    }

    public function create()
    {
        return view('patients.create', [
            'tutors' => $this->availableTutors(),
        ]);
    }

    public function edit(int $id)
    {
        return view('patients.edit', [
            'item' => $this->service->findOrFail($id),
            'tutors' => $this->availableTutors(),
        ]);
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
}
