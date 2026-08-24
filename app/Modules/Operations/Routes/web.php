<?php

use App\Modules\Operations\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('/operations', [OperationsController::class, 'index'])
    ->name('operations.index');

Route::post('/operations/smoke-checks/{checkKey}', [OperationsController::class, 'storeSmokeCheck'])
    ->where('checkKey', '[a-z_]+')
    ->name('operations.smoke-checks.store');
