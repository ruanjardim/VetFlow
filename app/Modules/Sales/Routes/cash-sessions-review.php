<?php

use App\Modules\Sales\Controllers\CashSessionController;
use Illuminate\Support\Facades\Route;

Route::post('sales/cash-sessions/{cashSession}/review', [CashSessionController::class, 'review'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.review');

Route::post('sales/cash-sessions/{cashSession}/reopen', [CashSessionController::class, 'reopen'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.reopen');
