<?php

use App\Modules\Hospitalizations\Controllers\HospitalizationController;
use App\Modules\Hospitalizations\Controllers\HospitalizationEvolutionController;
use Illuminate\Support\Facades\Route;

Route::post('hospitalizations/{hospitalization}/evolutions', [HospitalizationEvolutionController::class, 'store'])
    ->whereNumber('hospitalization')
    ->name('hospitalizations.evolutions.store');

Route::resource('hospitalizations', HospitalizationController::class)
    ->except(['show', 'destroy'])
    ->names('hospitalizations');
