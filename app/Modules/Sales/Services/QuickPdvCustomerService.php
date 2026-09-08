<?php

namespace App\Modules\Sales\Services;

use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Services\PatientService;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Tutors\Services\TutorService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class QuickPdvCustomerService
{
    public function __construct(
        private readonly TutorService $tutors,
        private readonly PatientService $patients,
        private readonly TenantContext $tenant
    ) {}

    /** @return array{tutor: Tutor, patient: Patient} */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $clinicId = $this->tenant->clinicId() ?? (int) $data['clinic_id'];

            /** @var Tutor $tutor */
            $tutor = $this->tutors->create([
                'clinic_id' => $clinicId,
                'name' => $data['tutor_name'],
                'phone' => $data['tutor_phone'],
                'email' => $data['tutor_email'] ?? null,
                'active' => true,
            ]);

            /** @var Patient $patient */
            $patient = $this->patients->create([
                'tutor_id' => $tutor->id,
                'name' => $data['patient_name'],
                'species' => $data['patient_species'] ?? null,
                'breed' => $data['patient_breed'] ?? null,
                'weight' => $data['patient_weight'] ?? null,
            ]);

            return compact('tutor', 'patient');
        });
    }
}
