<?php

namespace App\Modules\Schedules\Services;

use App\Core\Base\BaseService;
use App\Modules\Schedules\Contracts\ScheduleRepositoryInterface;

class ScheduleService extends BaseService
{
    public function __construct(ScheduleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}