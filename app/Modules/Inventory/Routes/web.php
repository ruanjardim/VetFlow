<?php

use App\Modules\Inventory\Controllers\InventoryCountController;
use App\Modules\Inventory\Controllers\InventoryMovementController;
use Illuminate\Support\Facades\Route;

Route::get('inventory-counts', [InventoryCountController::class, 'index'])
    ->name('inventory-counts.index');
Route::get('inventory-counts/create', [InventoryCountController::class, 'create'])
    ->name('inventory-counts.create');
Route::post('inventory-counts', [InventoryCountController::class, 'store'])
    ->name('inventory-counts.store');
Route::get('inventory-counts/{inventoryCount}', [InventoryCountController::class, 'show'])
    ->whereNumber('inventoryCount')
    ->name('inventory-counts.show');
Route::put('inventory-counts/{inventoryCount}', [InventoryCountController::class, 'update'])
    ->whereNumber('inventoryCount')
    ->name('inventory-counts.update');
Route::post('inventory-counts/{inventoryCount}/finalize', [InventoryCountController::class, 'finalize'])
    ->whereNumber('inventoryCount')
    ->name('inventory-counts.finalize');
Route::post('inventory-counts/{inventoryCount}/cancel', [InventoryCountController::class, 'cancel'])
    ->whereNumber('inventoryCount')
    ->name('inventory-counts.cancel');

Route::get('inventory-movements/radar', [InventoryMovementController::class, 'radar'])
    ->name('inventory-movements.radar');

Route::get('inventory-movements/alerts', [InventoryMovementController::class, 'alerts'])
    ->name('inventory-movements.alerts');

Route::get('inventory-movements/product-lookup/{gtin}', [InventoryMovementController::class, 'lookupProduct'])
    ->where('gtin', '[0-9A-Za-z-]+')
    ->name('inventory-movements.product-lookup');

Route::resource('inventory-movements', InventoryMovementController::class)
    ->except(['show'])
    ->names('inventory-movements');
