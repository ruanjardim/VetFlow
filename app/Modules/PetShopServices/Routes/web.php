<?php

use App\Modules\PetShopServices\Controllers\PetShopServiceController;
use Illuminate\Support\Facades\Route;

Route::resource('petshop-services', PetShopServiceController::class)
    ->except(['show'])
    ->names('petshop-services');
