<?php

namespace App\Modules\Schedules\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Schedules\Contracts\ScheduleRepositoryInterface;
use App\Modules\Schedules\Models\Schedule;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    public function __construct(Schedule $schedule)
    {
        $this->model = $schedule;
    }
}