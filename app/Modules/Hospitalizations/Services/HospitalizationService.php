<?php

namespace App\Modules\Hospitalizations\Services;

use App\Core\Base\BaseService;
use App\Modules\Hospitalizations\Contracts\HospitalizationRepositoryInterface;
use App\Modules\Hospitalizations\Models\Hospitalization;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class HospitalizationService extends BaseService
{
    public function __construct(HospitalizationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $patient = Patient::query()->findOrFail($data['patient_id']);

        $this->assertMedicalRecordMatchesPatient($data['medical_record_id'] ?? null, $patient);

        $data['clinic_id'] = $patient->clinic_id;
        $data['admitted_by'] = auth()->id();

        return parent::create($data);
    }

    public function update(int $id, array $data): Model
    {
        /** @var Hospitalization $hospitalization */
        $hospitalization = $this->repository->findOrFail($id);

        $this->assertMedicalRecordMatchesPatient($data['medical_record_id'] ?? null, $hospitalization->patient);

        $data['clinic_id'] = $hospitalization->clinic_id;
        $data['patient_id'] = $hospitalization->patient_id;

        return parent::update($id, $data);
    }

    private function assertMedicalRecordMatchesPatient(?int $medicalRecordId, Patient $patient): void
    {
        if (! $medicalRecordId) {
            return;
        }

        $medicalRecord = MedicalRecord::query()->find($medicalRecordId);

        if (
            ! $medicalRecord
            || (int) $medicalRecord->patient_id !== (int) $patient->id
            || (int) $medicalRecord->clinic_id !== (int) $patient->clinic_id
        ) {
            throw ValidationException::withMessages([
                'medical_record_id' => 'O prontuário relacionado deve pertencer ao paciente internado.',
            ]);
        }
    }
}
