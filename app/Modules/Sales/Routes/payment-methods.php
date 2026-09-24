<?php

use App\Modules\Sales\Controllers\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::get('sales/payment-methods', [PaymentMethodController::class, 'index'])
    ->name('sales.payment-methods.index');

Route::get('sales/payment-methods/create', [PaymentMethodController::class, 'create'])
    ->name('sales.payment-methods.create');

Route::post('sales/payment-methods', [PaymentMethodController::class, 'store'])
    ->name('sales.payment-methods.store');

Route::get('sales/payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])
    ->whereNumber('paymentMethod')
    ->name('sales.payment-methods.edit');

Route::put('sales/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
    ->whereNumber('paymentMethod')
    ->name('sales.payment-methods.update');

Route::get('sales/receivables', [PaymentMethodController::class, 'receivables'])
    ->name('sales.receivables');
