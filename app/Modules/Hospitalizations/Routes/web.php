<?php

use App\Modules\Hospitalizations\Controllers\HospitalizationController;
use Illuminate\Support\Facades\Route;

Route::resource('hospitalizations', HospitalizationController::class)
    ->except(['show', 'destroy'])
    ->names('hospitalizations');
