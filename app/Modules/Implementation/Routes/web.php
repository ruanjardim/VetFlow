<?php

use App\Modules\Implementation\Controllers\ImplementationController;
use Illuminate\Support\Facades\Route;

Route::get('implementation', [ImplementationController::class, 'index'])
    ->name('implementation.index');
