<?php

namespace App\Modules\Patients\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Patients\Models\Patient;

class PatientRepository extends BaseRepository implements PatientRepositoryInterface
{
    public function __construct(Patient $model)
    {
        $this->model = $model;
    }
}
