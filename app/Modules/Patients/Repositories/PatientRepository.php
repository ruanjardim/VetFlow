<?php

namespace App\Modules\Patients\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Patients\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PatientRepository extends BaseRepository implements PatientRepositoryInterface
{
    public function __construct(Patient $model)
    {
        $this->model = $model;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['tutor', 'animalSpecies', 'animalBreed'])
            ->orderBy('name')
            ->paginate($perPage);
    }
}
