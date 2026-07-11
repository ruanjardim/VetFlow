<?php

use App\Modules\Inventory\Controllers\InventoryMovementController;
use Illuminate\Support\Facades\Route;

Route::get('inventory-movements/alerts', [InventoryMovementController::class, 'alerts'])
    ->name('inventory-movements.alerts');

Route::get('inventory-movements/product-lookup/{gtin}', [InventoryMovementController::class, 'lookupProduct'])
    ->where('gtin', '[0-9A-Za-z-]+')
    ->name('inventory-movements.product-lookup');

Route::resource('inventory-movements', InventoryMovementController::class)
    ->except(['show'])
    ->names('inventory-movements');
