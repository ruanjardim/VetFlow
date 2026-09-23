<?php

use App\Modules\Commissions\Controllers\CommissionController;
use App\Modules\Commissions\Controllers\GroomingCommissionController;
use Illuminate\Support\Facades\Route;

Route::get('commissions/grooming', [GroomingCommissionController::class, 'index'])
    ->name('grooming-commissions.index');

Route::post('commissions/grooming/settlements', [GroomingCommissionController::class, 'settle'])
    ->name('grooming-commissions.settle');

Route::resource('commissions', CommissionController::class)
    ->except(['show', 'destroy'])
    ->names('commissions');
