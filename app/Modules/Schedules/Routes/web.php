<?php

use App\Modules\Schedules\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::resource('schedules', ScheduleController::class)
    ->except(['show'])
    ->names('schedules');
