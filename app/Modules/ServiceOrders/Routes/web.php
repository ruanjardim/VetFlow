<?php

use App\Modules\PetShopServices\Controllers\PetPackageController;
use App\Modules\ServiceOrders\Controllers\GroomingScheduleController;
use App\Modules\ServiceOrders\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('service-orders/board', [ServiceOrderController::class, 'board'])
    ->name('service-orders.board');

Route::get('service-orders/agenda', [ServiceOrderController::class, 'agenda'])
    ->name('service-orders.agenda');

Route::get('service-orders/availability', [ServiceOrderController::class, 'availability'])
    ->name('service-orders.availability');

Route::get('service-orders/grooming-settings', [GroomingScheduleController::class, 'index'])
    ->name('service-orders.grooming-settings');

Route::put('service-orders/grooming-settings', [GroomingScheduleController::class, 'update'])
    ->name('service-orders.grooming-settings.update');

Route::post('service-orders/grooming-settings/blocks', [GroomingScheduleController::class, 'storeBlock'])
    ->name('service-orders.grooming-settings.blocks.store');

Route::delete('service-orders/grooming-settings/blocks/{block}', [GroomingScheduleController::class, 'destroyBlock'])
    ->whereNumber('block')
    ->name('service-orders.grooming-settings.blocks.destroy');

Route::patch('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus'])
    ->whereNumber('serviceOrder')
    ->name('service-orders.status');

Route::resource('service-orders', ServiceOrderController::class)
    ->except(['show'])
    ->names('service-orders');

Route::get('pet-packages', [PetPackageController::class, 'index'])
    ->name('pet-packages.index');
Route::get('pet-packages/create', [PetPackageController::class, 'create'])
    ->name('pet-packages.create');
Route::post('pet-packages', [PetPackageController::class, 'store'])
    ->name('pet-packages.store');
Route::get('pet-packages/{petPackage}', [PetPackageController::class, 'show'])
    ->whereNumber('petPackage')
    ->name('pet-packages.show');
Route::patch('pet-packages/{petPackage}/activate', [PetPackageController::class, 'activate'])
    ->whereNumber('petPackage')
    ->name('pet-packages.activate');
Route::patch('pet-packages/{petPackage}/cancel', [PetPackageController::class, 'cancel'])
    ->whereNumber('petPackage')
    ->name('pet-packages.cancel');
