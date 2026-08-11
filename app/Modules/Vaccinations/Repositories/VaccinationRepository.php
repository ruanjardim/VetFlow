<?php

namespace App\Modules\Vaccinations\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Vaccinations\Contracts\VaccinationRepositoryInterface;
use App\Modules\Vaccinations\Models\Vaccination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class VaccinationRepository extends BaseRepository implements VaccinationRepositoryInterface
{
    public function __construct(Vaccination $vaccination)
    {
        $this->model = $vaccination;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['patient', 'medicalRecord'])
            ->orderBy('status')
            ->orderBy('scheduled_for')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->with(['patient', 'medicalRecord'])->findOrFail($id);
    }
}
