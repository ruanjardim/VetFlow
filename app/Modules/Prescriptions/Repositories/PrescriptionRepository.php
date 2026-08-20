<?php

namespace App\Modules\Prescriptions\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Prescriptions\Contracts\PrescriptionRepositoryInterface;
use App\Modules\Prescriptions\Models\Prescription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class PrescriptionRepository extends BaseRepository implements PrescriptionRepositoryInterface
{
    public function __construct(Prescription $prescription)
    {
        $this->model = $prescription;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'medicalRecord', 'createdBy'])
            ->latest('prescribed_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()
            ->with([
                'patient.tutor',
                'medicalRecord.appointment',
                'createdBy',
                'finalizedBy',
                'cancelledBy',
                'items',
            ])
            ->findOrFail($id);
    }
}
