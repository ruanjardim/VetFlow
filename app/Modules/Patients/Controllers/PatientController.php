<?php

namespace App\Modules\Patients\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Patients\Services\PatientService;

class PatientController extends BaseCrudController
{
    public function __construct(PatientService $service)
    {
        $this->service = $service;
        $this->viewPath = 'patients';
        $this->routeName = 'patients';
        $this->viewVariable = 'patients';
    }
}
