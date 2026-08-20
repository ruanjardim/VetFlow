<?php

namespace App\Modules\MedicalRecords\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\MedicalRecords\Contracts\MedicalRecordRepositoryInterface;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class MedicalRecordRepository extends BaseRepository implements MedicalRecordRepositoryInterface
{
    public function __construct(MedicalRecord $medicalRecord)
    {
        $this->model = $medicalRecord;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['appointment', 'patient', 'createdBy', 'pathologies', 'examRequests.result'])
            ->latest('examined_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()
            ->with([
                'appointment.tutor',
                'patient.tutor',
                'patient.animalSpecies',
                'patient.activeClinicalAlerts.createdBy',
                'createdBy',
                'pathologies.species',
                'examRequests.exam',
                'examRequests.result.createdBy',
                'examRequests.result.finalizedBy',
                'examRequests.result.cancelledBy',
            ])
            ->findOrFail($id);
    }
}
