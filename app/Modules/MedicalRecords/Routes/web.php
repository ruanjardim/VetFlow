<?php

use App\Modules\MedicalRecords\Controllers\MedicalRecordController;
use Illuminate\Support\Facades\Route;

Route::resource('medical-records', MedicalRecordController::class)
    ->except(['destroy'])
    ->names('medical-records');
