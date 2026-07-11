<?php

namespace App\Modules\Clinics\Services;

use App\Core\Base\BaseService;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;

class ClinicService extends BaseService
{
    public function __construct(ClinicRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}