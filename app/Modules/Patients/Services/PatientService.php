<?php

namespace App\Modules\Patients\Services;

use App\Core\Base\BaseService;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PatientService extends BaseService
{
    public function __construct(PatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $tutor = $this->resolveTutor($data);
        $data['clinic_id'] = $tutor->clinic_id;

        return parent::create($data);
    }

    public function update(int $id, array $data): Model
    {
        $patient = $this->repository->findOrFail($id);
        $tutor = $this->resolveTutor($data);

        if (
            $patient->clinic_id !== null
            && (int) $patient->clinic_id !== (int) $tutor->clinic_id
        ) {
            throw ValidationException::withMessages([
                'tutor_id' => 'O tutor responsável deve pertencer à mesma clínica do paciente.',
            ]);
        }

        $data['clinic_id'] = $tutor->clinic_id;
        $this->repository->update($patient, $data);

        return $patient->refresh();
    }

    private function resolveTutor(array $data): Tutor
    {
        return Tutor::query()->findOrFail((int) ($data['tutor_id'] ?? 0));
    }
}
