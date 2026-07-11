<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Clinics\Controllers\ClinicController;

Route::prefix('clinics')
    ->name('clinics.')
    ->group(function () {
        Route::get('/', [ClinicController::class, 'index'])
            ->name('index');

        Route::get('/create', [ClinicController::class, 'create'])
            ->name('create');

        Route::post('/', [ClinicController::class, 'store'])
            ->name('store');

        Route::get('/{clinic}/edit', [ClinicController::class, 'edit'])
            ->name('edit');

        Route::put('/{clinic}', [ClinicController::class, 'update'])
            ->name('update');

        Route::delete('/{clinic}', [ClinicController::class, 'destroy'])
            ->name('destroy');
    });