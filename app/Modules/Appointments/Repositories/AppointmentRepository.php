<?php

namespace App\Modules\Appointments\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Appointments\Contracts\AppointmentRepositoryInterface;
use App\Modules\Appointments\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AppointmentRepository extends BaseRepository implements AppointmentRepositoryInterface
{
    public function __construct(Appointment $appointment)
    {
        $this->model = $appointment;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'tutor', 'medicalRecord'])
            ->latest('scheduled_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()
            ->with(['patient', 'tutor', 'medicalRecord'])
            ->findOrFail($id);
    }
}
