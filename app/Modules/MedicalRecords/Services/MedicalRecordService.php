<?php

namespace App\Modules\MedicalRecords\Services;

use App\Core\Base\BaseService;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\MedicalRecords\Contracts\MedicalRecordRepositoryInterface;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalRecordService extends BaseService
{
    public function __construct(
        MedicalRecordRepositoryInterface $repository,
        private readonly PathologyCatalogService $pathologies
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $appointment = Appointment::query()->findOrFail($data['appointment_id']);

        if ((int) $appointment->patient_id !== (int) $data['patient_id']) {
            throw ValidationException::withMessages([
                'patient_id' => 'O paciente deve ser o mesmo vinculado à consulta selecionada.',
            ]);
        }

        $patient = Patient::query()->findOrFail($data['patient_id']);
        $clinicId = (int) $appointment->clinic_id;
        $creatorId = auth()->id();

        return DB::transaction(function () use ($data, $patient, $clinicId, $creatorId): Model {
            $pathologyIds = $this->pathologies->resolveForRecord(
                $data['pathology_ids'] ?? [],
                $data['new_pathology'] ?? null,
                $patient,
                $clinicId
            );

            unset($data['pathology_ids'], $data['new_pathology']);
            $data['clinic_id'] = $clinicId;
            $data['created_by'] = $creatorId;

            /** @var MedicalRecord $medicalRecord */
            $medicalRecord = parent::create($data);
            $medicalRecord->pathologies()->sync($pathologyIds);

            return $medicalRecord;
        });
    }

    public function update(int $id, array $data): Model
    {
        /** @var MedicalRecord $medicalRecord */
        $medicalRecord = $this->repository->findOrFail($id);
        $patient = $medicalRecord->patient()->firstOrFail();
        $clinicId = (int) $medicalRecord->clinic_id;

        return DB::transaction(function () use ($medicalRecord, $patient, $clinicId, $data): Model {
            $pathologyIds = $this->pathologies->resolveForRecord(
                $data['pathology_ids'] ?? [],
                $data['new_pathology'] ?? null,
                $patient,
                $clinicId
            );

            unset($data['pathology_ids'], $data['new_pathology']);
            $this->repository->update($medicalRecord, $data);
            $medicalRecord->pathologies()->sync($pathologyIds);

            return $medicalRecord->refresh()->load('pathologies');
        });
    }
}
