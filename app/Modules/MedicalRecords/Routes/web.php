<?php

use App\Modules\MedicalRecords\Controllers\ExamCatalogController;
use App\Modules\MedicalRecords\Controllers\MedicalRecordController;
use App\Modules\MedicalRecords\Controllers\PathologyCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('catalog/pathologies', [PathologyCatalogController::class, 'index'])
    ->name('pathology-catalog.index');
Route::post('catalog/pathologies', [PathologyCatalogController::class, 'store'])
    ->name('pathology-catalog.store');
Route::get('catalog/exams', [ExamCatalogController::class, 'index'])
    ->name('exam-catalog.index');
Route::post('catalog/exams', [ExamCatalogController::class, 'store'])
    ->name('exam-catalog.store');

Route::resource('medical-records', MedicalRecordController::class)
    ->except(['destroy'])
    ->names('medical-records');
