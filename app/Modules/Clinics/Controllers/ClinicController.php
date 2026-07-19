<?php

namespace App\Modules\Clinics\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Clinics\Requests\StoreClinicRequest;
use App\Modules\Clinics\Requests\UpdateClinicRequest;
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

    protected function storeRequest(): string
    {
        return StoreClinicRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateClinicRequest::class;
    }
}
