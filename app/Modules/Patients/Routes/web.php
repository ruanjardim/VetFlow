<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Patients\Controllers\PatientController;

Route::resource('patients', PatientController::class)
    ->except(['show'])
    ->names('patients');
