<?php

use App\Modules\Clinics\Controllers\ClinicBrandingController;
use Illuminate\Support\Facades\Route;

Route::get('settings/branding', [ClinicBrandingController::class, 'edit'])
    ->name('clinic-branding.edit');
Route::put('settings/branding', [ClinicBrandingController::class, 'update'])
    ->name('clinic-branding.update');
