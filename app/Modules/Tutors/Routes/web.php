<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Tutors\Controllers\TutorController;

Route::resource('tutores', TutorController::class)
    ->except(['show'])
    ->names('tutores');
