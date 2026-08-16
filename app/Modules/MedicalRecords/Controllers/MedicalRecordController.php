<?php

namespace App\Modules\MedicalRecords\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\MedicalRecords\Requests\StoreMedicalRecordRequest;
use App\Modules\MedicalRecords\Requests\UpdateMedicalRecordRequest;
use App\Modules\MedicalRecords\Services\MedicalRecordService;
use App\Modules\MedicalRecords\Services\ExamCatalogService;
use App\Modules\MedicalRecords\Services\PathologyCatalogService;
use App\Modules\Patients\Models\Patient;
use App\Support\Tenancy\TenantContext;

class MedicalRecordController extends BaseCrudController
{
    public function __construct(
        MedicalRecordService $service,
        private readonly PathologyCatalogService $pathologies,
        private readonly ExamCatalogService $exams,
        private readonly TenantContext $tenant
    ) {
        $this->service = $service;
        $this->viewPath = 'medical-records';
        $this->routeName = 'medical-records';
        $this->viewVariable = 'medicalRecords';
    }

    public function create()
    {
        $preselectedAppointmentId = (int) request()->query('appointment_id');

        return view("{$this->viewPath}.create", array_merge($this->formData(null, $preselectedAppointmentId), [
            'preselectedAppointmentId' => $preselectedAppointmentId,
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

    private function formData(?MedicalRecord $medicalRecord = null, ?int $preselectedAppointmentId = null): array
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

        $catalogClinicId = $this->tenant->clinicId();

        if ($catalogClinicId === null && $medicalRecord?->clinic_id) {
            $catalogClinicId = (int) $medicalRecord->clinic_id;
        }

        if ($catalogClinicId === null && $preselectedAppointmentId) {
            $catalogClinicId = (int) $appointments->firstWhere('id', $preselectedAppointmentId)?->clinic_id ?: null;
        }

        return [
            'appointments' => $appointments,
            'patients' => Patient::query()->with('animalSpecies')->orderBy('name')->get(),
            'pathologyRows' => $this->pathologies->formCatalog($catalogClinicId),
            'examRows' => $this->exams->formCatalog($catalogClinicId),
            'selectedPathologyIds' => old(
                'pathology_ids',
                $medicalRecord?->pathologies?->pluck('id')->all() ?? []
            ),
            'selectedExamIds' => old(
                'exam_ids',
                $medicalRecord?->examRequests?->pluck('animal_exam_id')->all() ?? []
            ),
        ];
    }
}
