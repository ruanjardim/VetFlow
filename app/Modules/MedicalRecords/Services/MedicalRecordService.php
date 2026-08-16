<?php

namespace App\Modules\MedicalRecords\Services;

use App\Core\Base\BaseService;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\MedicalRecords\Contracts\MedicalRecordRepositoryInterface;
use App\Modules\MedicalRecords\Models\AnimalExam;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalRecordService extends BaseService
{
    public function __construct(
        MedicalRecordRepositoryInterface $repository,
        private readonly PathologyCatalogService $pathologies,
        private readonly ExamCatalogService $exams
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
            $examIds = $this->exams->resolveForRecord(
                $data['exam_ids'] ?? [],
                $patient,
                $clinicId
            );

            unset($data['pathology_ids'], $data['new_pathology'], $data['exam_ids']);
            $data['clinic_id'] = $clinicId;
            $data['created_by'] = $creatorId;

            /** @var MedicalRecord $medicalRecord */
            $medicalRecord = parent::create($data);
            $medicalRecord->pathologies()->sync($pathologyIds);
            $this->syncExamRequests($medicalRecord, $examIds);

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
            $examIds = $this->exams->resolveForRecord(
                $data['exam_ids'] ?? [],
                $patient,
                $clinicId
            );

            unset($data['pathology_ids'], $data['new_pathology'], $data['exam_ids']);
            $this->repository->update($medicalRecord, $data);
            $medicalRecord->pathologies()->sync($pathologyIds);
            $this->syncExamRequests($medicalRecord, $examIds);

            return $medicalRecord->refresh()->load(['pathologies', 'examRequests.exam']);
        });
    }

    /** @param array<int, int> $examIds */
    private function syncExamRequests(MedicalRecord $medicalRecord, array $examIds): void
    {
        $medicalRecord->examRequests()->delete();

        if ($examIds === []) {
            return;
        }

        $exams = AnimalExam::query()
            ->whereIn('id', $examIds)
            ->get()
            ->keyBy('id');

        $medicalRecord->examRequests()->createMany(
            collect($examIds)
                ->map(fn (int $examId): array => [
                    'animal_exam_id' => $examId,
                    'exam_name' => $exams->get($examId)->name,
                ])
                ->all()
        );
    }
}
