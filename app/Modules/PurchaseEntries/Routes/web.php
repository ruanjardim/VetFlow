<?php

use App\Modules\PurchaseEntries\Controllers\PurchaseEntryController;
use Illuminate\Support\Facades\Route;

Route::get('purchase-entries/replenishment', [PurchaseEntryController::class, 'replenishment'])
    ->name('purchase-entries.replenishment');

Route::get('purchase-entries/replenishment/reviews', [PurchaseEntryController::class, 'replenishmentReviews'])
    ->name('purchase-entries.replenishment-reviews');

Route::get('purchase-entries/replenishment/purchases', [PurchaseEntryController::class, 'replenishmentPurchases'])
    ->name('purchase-entries.replenishment-purchases');

Route::post('purchase-entries/replenishment/{product}/reviews', [PurchaseEntryController::class, 'storeReplenishmentReview'])
    ->whereNumber('product')
    ->name('purchase-entries.replenishment-reviews.store');

Route::get('purchase-entries/product-lookup/{gtin}', [PurchaseEntryController::class, 'lookupProduct'])
    ->where('gtin', '[0-9A-Za-z-]+')
    ->name('purchase-entries.product-lookup');

Route::post('purchase-entries/import-nfe-xml', [PurchaseEntryController::class, 'importNfeXml'])
    ->name('purchase-entries.import-nfe-xml');

Route::post('purchase-entries/import-nfe-key', [PurchaseEntryController::class, 'importNfeKey'])
    ->name('purchase-entries.import-nfe-key');

Route::resource('purchase-entries', PurchaseEntryController::class)
    ->except(['show'])
    ->names('purchase-entries');
