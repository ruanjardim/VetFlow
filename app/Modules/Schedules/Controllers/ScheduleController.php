<?php

namespace App\Modules\Schedules\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Schedules\Requests\StoreScheduleRequest;
use App\Modules\Schedules\Requests\UpdateScheduleRequest;
use App\Modules\Schedules\Services\ScheduleService;

class ScheduleController extends BaseCrudController
{
    public function __construct(ScheduleService $service)
    {
        $this->service = $service;
        $this->viewPath = 'schedules';
        $this->routeName = 'schedules';
        $this->viewVariable = 'schedules';
    }

    protected function storeRequest(): string
    {
        return StoreScheduleRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateScheduleRequest::class;
    }
}
