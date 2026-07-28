<?php

use App\Modules\Implementation\Controllers\ImplementationController;
use Illuminate\Support\Facades\Route;

Route::get('implementation/templates/{template}', [ImplementationController::class, 'template'])
    ->where('template', '[a-z-]+')
    ->name('implementation.templates');

Route::post('implementation/clinic', [ImplementationController::class, 'selectClinic'])
    ->name('implementation.clinic');

Route::post('implementation/source', [ImplementationController::class, 'selectSource'])
    ->name('implementation.source');

Route::post('implementation/tutors/upload', [ImplementationController::class, 'uploadTutors'])
    ->name('implementation.tutors.upload');

Route::post('implementation/tutors/import', [ImplementationController::class, 'importTutors'])
    ->name('implementation.tutors.import');

Route::post('implementation/patients/upload', [ImplementationController::class, 'uploadPatients'])
    ->name('implementation.patients.upload');

Route::post('implementation/patients/import', [ImplementationController::class, 'importPatients'])
    ->name('implementation.patients.import');

Route::delete('implementation', [ImplementationController::class, 'reset'])
    ->name('implementation.reset');

Route::get('implementation', [ImplementationController::class, 'index'])
    ->name('implementation.index');
