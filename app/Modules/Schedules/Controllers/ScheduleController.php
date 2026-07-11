<?php

namespace App\Modules\Schedules\Controllers;

use App\Core\Base\BaseCrudController;
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
}