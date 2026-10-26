<?php

use App\Modules\Financial\Controllers\FinancialBankReconciliationController;
use App\Modules\Financial\Controllers\FinancialBudgetController;
use App\Modules\Financial\Controllers\FinancialCashFlowController;
use App\Modules\Financial\Controllers\FinancialCategoryController;
use App\Modules\Financial\Controllers\FinancialManagerialResultController;
use App\Modules\Financial\Controllers\FinancialObligationController;
use App\Modules\Financial\Controllers\FinancialTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('financial-bank-reconciliation', [FinancialBankReconciliationController::class, 'index'])
    ->name('financial-bank-reconciliation.index');
Route::get('financial-bank-reconciliation/export', [FinancialBankReconciliationController::class, 'export'])
    ->name('financial-bank-reconciliation.export');
Route::post('financial-bank-reconciliation/accounts', [FinancialBankReconciliationController::class, 'storeAccount'])
    ->name('financial-bank-reconciliation.accounts.store');
Route::put('financial-bank-reconciliation/accounts/{account}', [FinancialBankReconciliationController::class, 'updateAccount'])
    ->whereNumber('account')
    ->name('financial-bank-reconciliation.accounts.update');
Route::post('financial-bank-reconciliation/imports', [FinancialBankReconciliationController::class, 'importStatement'])
    ->name('financial-bank-reconciliation.imports.store');
Route::post('financial-bank-reconciliation/reconciliations', [FinancialBankReconciliationController::class, 'reconcile'])
    ->name('financial-bank-reconciliation.reconciliations.store');
Route::post('financial-bank-reconciliation/reconciliation-groups', [FinancialBankReconciliationController::class, 'reconcileGroup'])
    ->name('financial-bank-reconciliation.reconciliation-groups.store');
Route::patch('financial-bank-reconciliation/reconciliations/{reconciliation}/reverse', [FinancialBankReconciliationController::class, 'reverse'])
    ->whereNumber('reconciliation')
    ->name('financial-bank-reconciliation.reconciliations.reverse');

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

Route::get('financial-transactions/cash-flow', [FinancialCashFlowController::class, 'index'])
    ->name('financial-transactions.cash-flow');

Route::get('financial-transactions/cash-flow/export', [FinancialCashFlowController::class, 'export'])
    ->name('financial-transactions.cash-flow.export');

Route::get('financial-transactions/cash-flow/transactions', [FinancialCashFlowController::class, 'transactions'])
    ->name('financial-transactions.cash-flow.transactions');

Route::get('financial-results', [FinancialManagerialResultController::class, 'index'])
    ->name('financial-results.index');

Route::get('financial-results/export', [FinancialManagerialResultController::class, 'export'])
    ->name('financial-results.export');

Route::get('financial-obligations', [FinancialObligationController::class, 'index'])
    ->name('financial-obligations.index');
Route::get('financial-obligations/export', [FinancialObligationController::class, 'export'])
    ->name('financial-obligations.export');
Route::post('financial-obligations/reviews', [FinancialObligationController::class, 'storeReview'])
    ->name('financial-obligations.reviews.store');

Route::get('financial-budgets', [FinancialBudgetController::class, 'index'])
    ->name('financial-budgets.index');

Route::get('financial-budgets/export', [FinancialBudgetController::class, 'export'])
    ->name('financial-budgets.export');

Route::get('financial-budgets/transactions', [FinancialBudgetController::class, 'transactions'])
    ->name('financial-budgets.transactions');

Route::put('financial-budgets', [FinancialBudgetController::class, 'update'])
    ->name('financial-budgets.update');

Route::post('financial-budgets/copy-previous', [FinancialBudgetController::class, 'copyPrevious'])
    ->name('financial-budgets.copy-previous');

Route::patch('financial-transactions/{financial_transaction}/pay', [FinancialTransactionController::class, 'pay'])
    ->name('financial-transactions.pay');

Route::patch('financial-transactions/{financial_transaction}/cancel', [FinancialTransactionController::class, 'cancel'])
    ->name('financial-transactions.cancel');

Route::resource('financial-transactions', FinancialTransactionController::class)
    ->except(['show'])
    ->names('financial-transactions');
