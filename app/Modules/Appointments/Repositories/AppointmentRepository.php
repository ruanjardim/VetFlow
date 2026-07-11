<?php

namespace App\Modules\Appointments\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Appointments\Contracts\AppointmentRepositoryInterface;
use App\Modules\Appointments\Models\Appointment;

class AppointmentRepository extends BaseRepository implements AppointmentRepositoryInterface
{
    public function __construct(Appointment $appointment)
    {
        $this->model = $appointment;
    }
}
