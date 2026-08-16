<?php

use App\Modules\Vaccinations\Controllers\VaccinationController;
use App\Modules\Vaccinations\Controllers\VaccineCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('catalog/vaccines', [VaccineCatalogController::class, 'index'])->name('vaccine-catalog.index');
Route::post('catalog/vaccines', [VaccineCatalogController::class, 'store'])->name('vaccine-catalog.store');

Route::resource('vaccinations', VaccinationController::class)
    ->except(['show', 'destroy'])
    ->names('vaccinations');
