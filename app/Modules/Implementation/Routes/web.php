<?php

use App\Modules\Implementation\Controllers\ImplementationController;
use Illuminate\Support\Facades\Route;

Route::get('implementation/templates/{template}', [ImplementationController::class, 'template'])
    ->where('template', '[a-z-]+')
    ->name('implementation.templates');

Route::post('implementation/clinic', [ImplementationController::class, 'selectClinic'])
    ->name('implementation.clinic');

Route::post('implementation/source', [ImplementationController::class, 'selectSource'])
    ->name('implementation.source');

Route::post('implementation/tutors/upload', [ImplementationController::class, 'uploadTutors'])
    ->name('implementation.tutors.upload');

Route::post('implementation/tutors/import', [ImplementationController::class, 'importTutors'])
    ->name('implementation.tutors.import');

Route::post('implementation/patients/upload', [ImplementationController::class, 'uploadPatients'])
    ->name('implementation.patients.upload');

Route::post('implementation/patients/import', [ImplementationController::class, 'importPatients'])
    ->name('implementation.patients.import');

Route::post('implementation/suppliers/upload', [ImplementationController::class, 'uploadSuppliers'])
    ->name('implementation.suppliers.upload');

Route::post('implementation/suppliers/import', [ImplementationController::class, 'importSuppliers'])
    ->name('implementation.suppliers.import');

Route::post('implementation/products/upload', [ImplementationController::class, 'uploadProducts'])
    ->name('implementation.products.upload');

Route::post('implementation/products/import', [ImplementationController::class, 'importProducts'])
    ->name('implementation.products.import');

Route::post('implementation/stock/upload', [ImplementationController::class, 'uploadStock'])
    ->name('implementation.stock.upload');

Route::post('implementation/stock/import', [ImplementationController::class, 'importStock'])
    ->name('implementation.stock.import');

Route::post('implementation/financial/upload', [ImplementationController::class, 'uploadFinancial'])
    ->name('implementation.financial.upload');

Route::post('implementation/financial/import', [ImplementationController::class, 'importFinancial'])
    ->name('implementation.financial.import');

Route::delete('implementation', [ImplementationController::class, 'reset'])
    ->name('implementation.reset');

Route::get('implementation', [ImplementationController::class, 'index'])
    ->name('implementation.index');
