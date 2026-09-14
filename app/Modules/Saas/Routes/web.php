<?php

use App\Modules\Saas\Controllers\SaasAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SaasAdminController::class, 'dashboard'])->name('dashboard');
Route::get('/plans', [SaasAdminController::class, 'plans'])->name('plans.index');
Route::get('/plans/create', [SaasAdminController::class, 'createPlan'])->name('plans.create');
Route::post('/plans', [SaasAdminController::class, 'storePlan'])->name('plans.store');
Route::get('/plans/{plan}/edit', [SaasAdminController::class, 'editPlan'])->name('plans.edit');
Route::put('/plans/{plan}', [SaasAdminController::class, 'updatePlan'])->name('plans.update');
Route::get('/establishments', [SaasAdminController::class, 'establishments'])->name('establishments.index');
Route::get('/establishments/{clinic}', [SaasAdminController::class, 'establishment'])->name('establishments.show');
Route::put('/establishments/{clinic}/subscription', [SaasAdminController::class, 'updateSubscription'])->name('subscriptions.update');
Route::get('/onboarding', [SaasAdminController::class, 'createOnboarding'])->name('onboarding.create');
Route::post('/onboarding', [SaasAdminController::class, 'storeOnboarding'])->name('onboarding.store');
