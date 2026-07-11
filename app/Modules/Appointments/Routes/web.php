<?php

use App\Modules\Appointments\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::resource('appointments', AppointmentController::class)
    ->except(['show'])
    ->names('appointments');
