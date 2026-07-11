<?php

use App\Modules\ServiceOrders\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::resource('service-orders', ServiceOrderController::class)
    ->except(['show'])
    ->names('service-orders');
