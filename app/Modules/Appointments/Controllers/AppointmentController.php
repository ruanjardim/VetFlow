<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Appointments\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Requests\UpdateAppointmentRequest;
use App\Modules\Appointments\Services\AppointmentService;

class AppointmentController extends BaseCrudController
{
    public function __construct(AppointmentService $service)
    {
        $this->service = $service;
        $this->viewPath = 'appointments';
        $this->routeName = 'appointments';
        $this->viewVariable = 'appointments';
    }

    protected function storeRequest(): string
    {
        return StoreAppointmentRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateAppointmentRequest::class;
    }
}
