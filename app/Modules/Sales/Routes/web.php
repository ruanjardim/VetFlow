<?php

use App\Modules\Sales\Controllers\CashSessionController;
use App\Modules\Sales\Controllers\CustomerBalanceController;
use App\Modules\Sales\Controllers\PdvSessionController;
use App\Modules\Sales\Controllers\SaleController;
use App\Modules\Sales\Controllers\SaleQuoteController;
use Illuminate\Support\Facades\Route;

Route::get('sales/pdv/session', [PdvSessionController::class, 'heartbeat'])
    ->middleware('throttle:30,1')
    ->name('sales.pdv.session');

Route::get('sales/pdv/offline-catalog', [SaleController::class, 'offlineCatalog'])
    ->middleware('throttle:12,1')
    ->name('sales.pdv.offline-catalog');

Route::post('sales/pdv/offline-sync', [SaleController::class, 'syncOffline'])
    ->middleware('throttle:30,1')
    ->name('sales.pdv.offline-sync');

Route::get('sales/customer-balances', [CustomerBalanceController::class, 'index'])
    ->name('sales.customer-balances.index');

Route::get('sales/customer-balances/{tutor}', [CustomerBalanceController::class, 'show'])
    ->whereNumber('tutor')
    ->name('sales.customer-balances.show');

Route::get('sales/customer-balances/{tutor}/summary', [CustomerBalanceController::class, 'summary'])
    ->whereNumber('tutor')
    ->name('sales.customer-balances.summary');

Route::post('sales/customer-balances/{tutor}/settle', [CustomerBalanceController::class, 'settle'])
    ->whereNumber('tutor')
    ->name('sales.customer-balances.settle');

Route::post('sales/customer-balances/{tutor}/deposit', [CustomerBalanceController::class, 'deposit'])
    ->whereNumber('tutor')
    ->name('sales.customer-balances.deposit');

Route::post('sales/customer-balances/{tutor}/refund', [CustomerBalanceController::class, 'refund'])
    ->whereNumber('tutor')
    ->name('sales.customer-balances.refund');

Route::get('sales/cash-sessions', [CashSessionController::class, 'index'])
    ->name('sales.cash-sessions.index');

Route::post('sales/cash-sessions', [CashSessionController::class, 'store'])
    ->name('sales.cash-sessions.store');

Route::get('sales/cash-sessions/{cashSession}', [CashSessionController::class, 'show'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.show');

Route::get('sales/cash-sessions/{cashSession}/receipt', [CashSessionController::class, 'receipt'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.receipt');

Route::post('sales/cash-sessions/{cashSession}/movements', [CashSessionController::class, 'storeMovement'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.movements.store');

Route::get('sales/cash-sessions/{cashSession}/close', [CashSessionController::class, 'closeForm'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.close');

Route::post('sales/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])
    ->whereNumber('cashSession')
    ->name('sales.cash-sessions.close.store');

Route::get('sales/quotes', [SaleQuoteController::class, 'index'])
    ->name('sales.quotes.index');

Route::post('sales/quotes', [SaleQuoteController::class, 'store'])
    ->name('sales.quotes.store');

Route::get('sales/quotes/{quote}', [SaleQuoteController::class, 'show'])
    ->whereNumber('quote')
    ->name('sales.quotes.show');

Route::put('sales/quotes/{quote}', [SaleQuoteController::class, 'update'])
    ->whereNumber('quote')
    ->name('sales.quotes.update');

Route::patch('sales/quotes/{quote}/cancel', [SaleQuoteController::class, 'cancel'])
    ->whereNumber('quote')
    ->name('sales.quotes.cancel');

Route::get('sales/product-lookup/{gtin}', [SaleController::class, 'lookupProduct'])
    ->where('gtin', '[0-9]+')
    ->name('sales.product-lookup');

Route::get('sales/quick-search', [SaleController::class, 'quickSearch'])
    ->name('sales.quick-search');

Route::post('sales/quick-products', [SaleController::class, 'storeQuickProduct'])
    ->name('sales.quick-products.store');

Route::get('sales/cashier', [SaleController::class, 'cashier'])
    ->name('sales.cashier');

Route::get('sales/profitability', [SaleController::class, 'profitability'])
    ->name('sales.profitability');

Route::get('sales/product-abc', [SaleController::class, 'productAbc'])
    ->name('sales.product-abc');

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
