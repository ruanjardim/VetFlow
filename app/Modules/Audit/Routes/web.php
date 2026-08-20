<?php

use App\Modules\Audit\Controllers\AuditEventController;
use Illuminate\Support\Facades\Route;

Route::get('audit', [AuditEventController::class, 'index'])
    ->name('audit-events.index');
