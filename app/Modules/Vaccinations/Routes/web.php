<?php

use App\Modules\Vaccinations\Controllers\VaccinationController;
use Illuminate\Support\Facades\Route;

Route::resource('vaccinations', VaccinationController::class)
    ->except(['show', 'destroy'])
    ->names('vaccinations');
