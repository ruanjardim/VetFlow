<?php

use App\Modules\Suppliers\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::resource('suppliers', SupplierController::class)
    ->except(['show'])
    ->names('suppliers');
