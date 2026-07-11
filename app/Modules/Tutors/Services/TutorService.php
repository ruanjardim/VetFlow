<?php

namespace App\Modules\Tutors\Services;

use App\Core\Base\BaseService;
use App\Modules\Tutors\Contracts\TutorRepositoryInterface;

class TutorService extends BaseService
{
    public function __construct(TutorRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}