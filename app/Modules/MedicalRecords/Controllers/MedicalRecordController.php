<?php

namespace App\Modules\MedicalRecords\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\MedicalRecords\Requests\StoreMedicalRecordRequest;
use App\Modules\MedicalRecords\Requests\UpdateMedicalRecordRequest;
use App\Modules\MedicalRecords\Services\MedicalRecordService;
use App\Modules\Patients\Models\Patient;

class MedicalRecordController extends BaseCrudController
{
    public function __construct(MedicalRecordService $service)
    {
        $this->service = $service;
        $this->viewPath = 'medical-records';
        $this->routeName = 'medical-records';
        $this->viewVariable = 'medicalRecords';
    }

    public function create()
    {
        return view("{$this->viewPath}.create", array_merge($this->formData(), [
            'preselectedAppointmentId' => (int) request()->query('appointment_id'),
            'preselectedPatientId' => (int) request()->query('patient_id'),
        ]));
    }

    public function edit(int $id)
    {
        $medicalRecord = $this->service->findOrFail($id);

        return view("{$this->viewPath}.edit", array_merge($this->formData($medicalRecord), [
            'medicalRecord' => $medicalRecord,
        ]));
    }

    public function show(int $id)
    {
        return view("{$this->viewPath}.show", [
            'medicalRecord' => $this->service->findOrFail($id),
        ]);
    }

    protected function storeRequest(): string
    {
        return StoreMedicalRecordRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateMedicalRecordRequest::class;
    }

    private function formData(?MedicalRecord $medicalRecord = null): array
    {
        $appointments = Appointment::query()
            ->with(['patient', 'tutor'])
            ->whereNotNull('patient_id')
            ->when(
                $medicalRecord,
                fn ($query) => $query->where(function ($query) use ($medicalRecord): void {
                    $query->whereDoesntHave('medicalRecord')
                        ->orWhereKey($medicalRecord->appointment_id);
                }),
                fn ($query) => $query->whereDoesntHave('medicalRecord')
            )
            ->latest('scheduled_at')
            ->get();

        return [
            'appointments' => $appointments,
            'patients' => Patient::query()->orderBy('name')->get(),
        ];
    }
}
