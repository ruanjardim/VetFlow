<?php

namespace App\Modules\Clinics\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Models\Clinic;

class ClinicRepository extends BaseRepository implements ClinicRepositoryInterface
{
    public function __construct(Clinic $clinic)
    {
        $this->model = $clinic;
    }
}