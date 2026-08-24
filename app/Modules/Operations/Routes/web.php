<?php

use App\Modules\Operations\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('/operations', [OperationsController::class, 'index'])
    ->name('operations.index');
