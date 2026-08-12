<?php

use App\Modules\Commissions\Controllers\CommissionController;
use Illuminate\Support\Facades\Route;

Route::resource('commissions', CommissionController::class)
    ->except(['show', 'destroy'])
    ->names('commissions');
