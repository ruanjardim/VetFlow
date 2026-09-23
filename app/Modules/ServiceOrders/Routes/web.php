<?php

use App\Modules\ServiceOrders\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('service-orders/board', [ServiceOrderController::class, 'board'])
    ->name('service-orders.board');

Route::get('service-orders/agenda', [ServiceOrderController::class, 'agenda'])
    ->name('service-orders.agenda');

Route::get('service-orders/availability', [ServiceOrderController::class, 'availability'])
    ->name('service-orders.availability');

Route::patch('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus'])
    ->whereNumber('serviceOrder')
    ->name('service-orders.status');

Route::resource('service-orders', ServiceOrderController::class)
    ->except(['show'])
    ->names('service-orders');

Route::get('pet-packages', [\App\Modules\PetShopServices\Controllers\PetPackageController::class, 'index'])
    ->name('pet-packages.index');
Route::get('pet-packages/create', [\App\Modules\PetShopServices\Controllers\PetPackageController::class, 'create'])
    ->name('pet-packages.create');
Route::post('pet-packages', [\App\Modules\PetShopServices\Controllers\PetPackageController::class, 'store'])
    ->name('pet-packages.store');
Route::get('pet-packages/{petPackage}', [\App\Modules\PetShopServices\Controllers\PetPackageController::class, 'show'])
    ->whereNumber('petPackage')
    ->name('pet-packages.show');
Route::patch('pet-packages/{petPackage}/activate', [\App\Modules\PetShopServices\Controllers\PetPackageController::class, 'activate'])
    ->whereNumber('petPackage')
    ->name('pet-packages.activate');
Route::patch('pet-packages/{petPackage}/cancel', [\App\Modules\PetShopServices\Controllers\PetPackageController::class, 'cancel'])
    ->whereNumber('petPackage')
    ->name('pet-packages.cancel');
