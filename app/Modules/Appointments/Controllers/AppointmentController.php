<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Appointments\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Requests\UpdateAppointmentRequest;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;

class AppointmentController extends BaseCrudController
{
    public function __construct(AppointmentService $service)
    {
        $this->service = $service;
        $this->viewPath = 'appointments';
        $this->routeName = 'appointments';
        $this->viewVariable = 'appointments';
    }

    public function create()
    {
        return view("{$this->viewPath}.create", $this->formData());
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", array_merge($this->formData(), [
            'item' => $this->service->findOrFail($id),
        ]));
    }

    protected function storeRequest(): string
    {
        return StoreAppointmentRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateAppointmentRequest::class;
    }

    private function formData(): array
    {
        return [
            'patients' => Patient::query()->orderBy('name')->get(),
            'tutors' => Tutor::query()->orderBy('name')->get(),
        ];
    }
}
