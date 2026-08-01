<?php

use App\Http\Middleware\EnsureUserIsGlobal;
use App\Modules\Clinics\Controllers\ClinicController;
use Illuminate\Support\Facades\Route;

Route::prefix('clinics')
    ->name('clinics.')
    ->middleware(EnsureUserIsGlobal::class)
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
