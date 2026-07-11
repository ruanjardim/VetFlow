<?php

namespace App\Modules\Tutors\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Tutors\Contracts\TutorRepositoryInterface;
use App\Modules\Tutors\Models\Tutor;

class TutorRepository extends BaseRepository implements TutorRepositoryInterface
{
    public function __construct(Tutor $tutor)
    {
        $this->model = $tutor;
    }
}