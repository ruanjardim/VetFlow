<?php

use App\Modules\ServiceOrders\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('service-orders/board', [ServiceOrderController::class, 'board'])
    ->name('service-orders.board');

Route::patch('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus'])
    ->whereNumber('serviceOrder')
    ->name('service-orders.status');

Route::resource('service-orders', ServiceOrderController::class)
    ->except(['show'])
    ->names('service-orders');
