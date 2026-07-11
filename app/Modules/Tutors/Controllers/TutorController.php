<?php

namespace App\Modules\Tutors\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Tutors\Requests\StoreTutorRequest;
use App\Modules\Tutors\Requests\UpdateTutorRequest;
use App\Modules\Tutors\Services\TutorService;

class TutorController extends BaseCrudController
{
    public function __construct(TutorService $service)
    {
        $this->service = $service;
        $this->viewPath = 'tutors';
        $this->routeName = 'tutores';
        $this->viewVariable = 'tutors';
    }

    protected function storeRequest(): string
    {
        return StoreTutorRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateTutorRequest::class;
    }
}