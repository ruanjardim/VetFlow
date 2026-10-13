<?php

use App\Modules\Financial\Controllers\FinancialCategoryController;
use App\Modules\Financial\Controllers\FinancialTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('financial-categories', [FinancialCategoryController::class, 'index'])
    ->name('financial-categories.index');

Route::get('financial-categories/create', [FinancialCategoryController::class, 'create'])
    ->name('financial-categories.create');

Route::post('financial-categories', [FinancialCategoryController::class, 'store'])
    ->name('financial-categories.store');

Route::get('financial-categories/{financialCategory}/edit', [FinancialCategoryController::class, 'edit'])
    ->whereNumber('financialCategory')
    ->name('financial-categories.edit');

Route::put('financial-categories/{financialCategory}', [FinancialCategoryController::class, 'update'])
    ->whereNumber('financialCategory')
    ->name('financial-categories.update');

Route::get('financial-transactions/cash-flow', [FinancialTransactionController::class, 'cashFlow'])
    ->name('financial-transactions.cash-flow');

Route::patch('financial-transactions/{financial_transaction}/pay', [FinancialTransactionController::class, 'pay'])
    ->name('financial-transactions.pay');

Route::patch('financial-transactions/{financial_transaction}/cancel', [FinancialTransactionController::class, 'cancel'])
    ->name('financial-transactions.cancel');

Route::resource('financial-transactions', FinancialTransactionController::class)
    ->except(['show'])
    ->names('financial-transactions');
