<?php

use App\Modules\PetShopServices\Controllers\PetShopServiceController;
use App\Modules\PetShopServices\Controllers\PetshopPackageController;
use Illuminate\Support\Facades\Route;

Route::resource('petshop-services', PetShopServiceController::class)
    ->except(['show'])
    ->names('petshop-services');

Route::resource('petshop-packages', PetshopPackageController::class)
    ->except(['show', 'destroy'])
    ->names('petshop-packages');
