<?php

namespace App\Modules\Hospitalizations\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Hospitalizations\Contracts\HospitalizationRepositoryInterface;
use App\Modules\Hospitalizations\Models\Hospitalization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class HospitalizationRepository extends BaseRepository implements HospitalizationRepositoryInterface
{
    public function __construct(Hospitalization $hospitalization)
    {
        $this->model = $hospitalization;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient.tutor', 'medicalRecord', 'admittedBy'])
            ->withCount('evolutions')
            ->orderByRaw("case when status = 'hospitalized' then 0 else 1 end")
            ->latest('admitted_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()
            ->with(['patient.tutor', 'medicalRecord', 'admittedBy', 'evolutions.recordedBy'])
            ->findOrFail($id);
    }
}
