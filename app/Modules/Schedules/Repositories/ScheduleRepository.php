<?php

namespace App\Modules\Schedules\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Schedules\Contracts\ScheduleRepositoryInterface;
use App\Modules\Schedules\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    public function __construct(Schedule $schedule)
    {
        $this->model = $schedule;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'tutor'])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('scheduled_time')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()
            ->with(['patient', 'tutor'])
            ->findOrFail($id);
    }
}
