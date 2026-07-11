<?php

namespace App\Modules\Patients\Services;

use App\Core\Base\BaseService;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;

class PatientService extends BaseService
{
    public function __construct(PatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}
