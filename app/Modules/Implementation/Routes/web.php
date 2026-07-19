<?php

use App\Modules\Implementation\Controllers\ImplementationController;
use Illuminate\Support\Facades\Route;

Route::get('implementation/templates/{template}', [ImplementationController::class, 'template'])
    ->where('template', '[a-z-]+')
    ->name('implementation.templates');

Route::get('implementation', [ImplementationController::class, 'index'])
    ->name('implementation.index');
