<?php

use App\Modules\ServiceOrders\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('service-orders/board', [ServiceOrderController::class, 'board'])
    ->name('service-orders.board');

Route::get('service-orders/agenda', [ServiceOrderController::class, 'agenda'])
    ->name('service-orders.agenda');

Route::get('service-orders/availability', [ServiceOrderController::class, 'availability'])
    ->name('service-orders.availability');

Route::patch('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus'])
    ->whereNumber('serviceOrder')
    ->name('service-orders.status');

Route::resource('service-orders', ServiceOrderController::class)
    ->except(['show'])
    ->names('service-orders');
