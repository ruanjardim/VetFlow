<?php

use App\Modules\Products\Controllers\ProductController;
use App\Modules\Products\Controllers\ProductLookupController;
use Illuminate\Support\Facades\Route;

Route::get('products/lookup-image/{filename}', [ProductLookupController::class, 'image'])
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('products.lookup-image');

Route::get('products/lookup/{gtin}', [ProductLookupController::class, 'show'])
    ->where('gtin', '[0-9A-Za-z-]+')
    ->name('products.lookup');

Route::resource('products', ProductController::class)
    ->except(['show'])
    ->names('products');
