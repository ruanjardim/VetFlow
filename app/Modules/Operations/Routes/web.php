<?php

use App\Modules\Operations\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('/operations', [OperationsController::class, 'index'])
    ->name('operations.index');

Route::get('/operations/report', [OperationsController::class, 'report'])
    ->name('operations.report');

Route::get('/operations/report.json', [OperationsController::class, 'reportJson'])
    ->name('operations.report.json');

Route::post('/operations/decision', [OperationsController::class, 'storeDecision'])
    ->name('operations.decision.store');

Route::post('/operations/smoke-checks/{checkKey}', [OperationsController::class, 'storeSmokeCheck'])
    ->where('checkKey', '[a-z_]+')
    ->name('operations.smoke-checks.store');
