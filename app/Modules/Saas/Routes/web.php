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
Route::post('/establishments/{clinic}/coupon', [SaasAdminController::class, 'applyCoupon'])->name('subscriptions.coupon.apply');
Route::delete('/establishments/{clinic}/promotions/{promotion}', [SaasAdminController::class, 'cancelPromotion'])->name('subscriptions.promotion.cancel');
Route::get('/onboarding', [SaasAdminController::class, 'createOnboarding'])->name('onboarding.create');
Route::post('/onboarding', [SaasAdminController::class, 'storeOnboarding'])->name('onboarding.store');
Route::get('/coupons', [SaasAdminController::class, 'coupons'])->name('coupons.index');
Route::get('/coupons/create', [SaasAdminController::class, 'createCoupon'])->name('coupons.create');
Route::post('/coupons', [SaasAdminController::class, 'storeCoupon'])->name('coupons.store');
Route::get('/coupons/{coupon}/edit', [SaasAdminController::class, 'editCoupon'])->name('coupons.edit');
Route::put('/coupons/{coupon}', [SaasAdminController::class, 'updateCoupon'])->name('coupons.update');
Route::get('/legal', [SaasAdminController::class, 'legalDocuments'])->name('legal.index');
Route::get('/legal/create', [SaasAdminController::class, 'createLegalDocument'])->name('legal.create');
Route::post('/legal', [SaasAdminController::class, 'storeLegalDocument'])->name('legal.store');
Route::post('/legal/{legalDocument}/publish', [SaasAdminController::class, 'publishLegalDocument'])->name('legal.publish');
