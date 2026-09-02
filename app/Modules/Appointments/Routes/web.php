<?php

use App\Modules\Appointments\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::get('appointments/reminders', [AppointmentController::class, 'reminders'])
    ->name('appointments.reminders');

Route::post('appointments/{appointment}/reminders', [AppointmentController::class, 'storeReminder'])
    ->whereNumber('appointment')
    ->name('appointments.reminders.store');

Route::resource('appointments', AppointmentController::class)
    ->except(['show'])
    ->names('appointments');
