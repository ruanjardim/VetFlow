<?php

use App\Modules\PurchaseEntries\Controllers\PurchaseEntryController;
use Illuminate\Support\Facades\Route;

Route::resource('purchase-entries', PurchaseEntryController::class)
    ->except(['show'])
    ->names('purchase-entries');
