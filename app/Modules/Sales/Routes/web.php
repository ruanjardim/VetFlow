<?php

use App\Modules\Sales\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('sales/product-lookup/{gtin}', [SaleController::class, 'lookupProduct'])
    ->where('gtin', '[0-9]+')
    ->name('sales.product-lookup');

Route::get('sales/cashier', [SaleController::class, 'cashier'])
    ->name('sales.cashier');

Route::get('sales/cashier/close', [SaleController::class, 'cashierClose'])
    ->name('sales.cashier.close');

Route::post('sales/cashier/close', [SaleController::class, 'storeCashierClose'])
    ->name('sales.cashier.close.store');

Route::post('sales/{sale}/payments', [SaleController::class, 'storePayment'])
    ->whereNumber('sale')
    ->name('sales.payments.store');

Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])
    ->whereNumber('sale')
    ->name('sales.receipt');

Route::patch('sales/{sale}/cancel', [SaleController::class, 'cancel'])
    ->whereNumber('sale')
    ->name('sales.cancel');

Route::get('sales/{sale}/returns/create', [SaleController::class, 'returnForm'])
    ->whereNumber('sale')
    ->name('sales.returns.create');

Route::post('sales/{sale}/returns', [SaleController::class, 'storeReturn'])
    ->whereNumber('sale')
    ->name('sales.returns.store');

Route::resource('sales', SaleController::class)
    ->except(['show'])
    ->names('sales');
