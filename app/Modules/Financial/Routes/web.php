<?php

use App\Modules\Financial\Controllers\FinancialTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('financial-transactions/cash-flow', [FinancialTransactionController::class, 'cashFlow'])
    ->name('financial-transactions.cash-flow');

Route::patch('financial-transactions/{financial_transaction}/pay', [FinancialTransactionController::class, 'pay'])
    ->name('financial-transactions.pay');

Route::patch('financial-transactions/{financial_transaction}/cancel', [FinancialTransactionController::class, 'cancel'])
    ->name('financial-transactions.cancel');

Route::resource('financial-transactions', FinancialTransactionController::class)
    ->except(['show'])
    ->names('financial-transactions');
