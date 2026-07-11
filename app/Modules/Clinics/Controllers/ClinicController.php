<?php

namespace App\Modules\Clinics\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Clinics\Services\ClinicService;

class ClinicController extends BaseCrudController
{
    public function __construct(ClinicService $service)
    {
        $this->service = $service;

        $this->viewPath = 'clinics';

        $this->routeName = 'clinics';

        $this->viewVariable = 'clinics';
    }
}