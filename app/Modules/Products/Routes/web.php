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

Route::get('products/diagnostics', [ProductController::class, 'diagnostics'])
    ->name('products.diagnostics');

Route::get('products/pricing-radar', [ProductController::class, 'pricingRadar'])
    ->name('products.pricing-radar');

Route::post('products/{product}/link-global', [ProductController::class, 'linkGlobal'])
    ->whereNumber('product')
    ->name('products.link-global');

Route::post('products/{product}/enrich', [ProductController::class, 'enrich'])
    ->whereNumber('product')
    ->name('products.enrich');

Route::post('products/{product}/sync-global', [ProductController::class, 'syncGlobal'])
    ->whereNumber('product')
    ->name('products.sync-global');

Route::resource('products', ProductController::class)
    ->except(['show'])
    ->names('products');
