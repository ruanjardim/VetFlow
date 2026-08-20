<?php

use App\Modules\Prescriptions\Controllers\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::patch('prescriptions/{prescription}/finalize', [PrescriptionController::class, 'finalize'])
    ->whereNumber('prescription')
    ->name('prescriptions.finalize');
Route::patch('prescriptions/{prescription}/cancel', [PrescriptionController::class, 'cancel'])
    ->whereNumber('prescription')
    ->name('prescriptions.cancel');

Route::resource('prescriptions', PrescriptionController::class)
    ->except(['destroy'])
    ->names('prescriptions');
