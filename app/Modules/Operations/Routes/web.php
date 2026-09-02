<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Modules\Operations\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('/operations', [OperationsController::class, 'index'])
    ->name('operations.index');

Route::get('/operations/report', [OperationsController::class, 'report'])
    ->name('operations.report');

Route::get('/operations/report.json', [OperationsController::class, 'reportJson'])
    ->name('operations.report.json');

Route::get('/operations/history', [OperationsController::class, 'history'])
    ->name('operations.history');

Route::middleware(EnsureUserHasPermission::class.':operations.execute')->group(function (): void {
    Route::post('/operations/decision', [OperationsController::class, 'storeDecision'])
        ->name('operations.decision.store');

    Route::post('/operations/smoke-checks/{checkKey}', [OperationsController::class, 'storeSmokeCheck'])
        ->where('checkKey', '[a-z_]+')
        ->name('operations.smoke-checks.store');

    Route::post('/operations/runtime-probes', [OperationsController::class, 'prepareRuntimeProbe'])
        ->name('operations.runtime-probes.prepare');

    Route::post('/operations/runtime-probes/{probeId}/verify', [OperationsController::class, 'verifyRuntimeProbe'])
        ->where('probeId', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('operations.runtime-probes.verify');

    Route::post('/operations/backup-evidence', [OperationsController::class, 'importBackupEvidence'])
        ->name('operations.backup-evidence.import');
});
