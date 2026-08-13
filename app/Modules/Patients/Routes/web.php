<?php

use App\Modules\Patients\Controllers\PatientCatalogController;
use App\Modules\Patients\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

Route::get('catalog/species', [PatientCatalogController::class, 'species'])
    ->name('patient-catalog.species');
Route::post('catalog/species', [PatientCatalogController::class, 'storeSpecies'])
    ->name('patient-catalog.species.store');
Route::get('catalog/breeds', [PatientCatalogController::class, 'breeds'])
    ->name('patient-catalog.breeds');
Route::post('catalog/breeds', [PatientCatalogController::class, 'storeBreed'])
    ->name('patient-catalog.breeds.store');
Route::get('catalog/coats', [PatientCatalogController::class, 'coats'])
    ->name('patient-catalog.coats');
Route::post('catalog/coats', [PatientCatalogController::class, 'storeCoat'])
    ->name('patient-catalog.coats.store');
Route::get('catalog/specialties', [PatientCatalogController::class, 'specialties'])
    ->name('patient-catalog.specialties');
Route::put('catalog/specialties', [PatientCatalogController::class, 'updateSpecialties'])
    ->name('patient-catalog.specialties.update');

Route::resource('patients', PatientController::class)
    ->except(['show'])
    ->names('patients');
