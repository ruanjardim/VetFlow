<?php

namespace App\Modules\MedicalRecords\Services;

use App\Core\Base\BaseService;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\MedicalRecords\Contracts\MedicalRecordRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MedicalRecordService extends BaseService
{
    public function __construct(MedicalRecordRepositoryInterface $repository)
    {
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

        $data['clinic_id'] = $appointment->clinic_id;
        $data['created_by'] = auth()->id();

        return parent::create($data);
    }
}
