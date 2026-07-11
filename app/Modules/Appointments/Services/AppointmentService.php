<?php

namespace App\Modules\Appointments\Services;

use App\Core\Base\BaseService;
use App\Modules\Appointments\Contracts\AppointmentRepositoryInterface;

class AppointmentService extends BaseService
{
    public function __construct(AppointmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}
