<?php

use App\Modules\Fiscal\Controllers\FiscalController;
use App\Modules\Fiscal\Controllers\FiscalDocumentCenterController;
use App\Modules\Fiscal\Controllers\FiscalProductController;
use App\Modules\Fiscal\Controllers\FiscalReconciliationController;
use App\Modules\Fiscal\Controllers\FiscalReturnReviewController;
use App\Modules\Fiscal\Controllers\FiscalServiceController;
use Illuminate\Support\Facades\Route;

Route::get('fiscal', [FiscalController::class, 'index'])->name('fiscal.index');
Route::put('fiscal/settings', [FiscalController::class, 'update'])->name('fiscal.update');
Route::post('fiscal/focus-credential', [FiscalController::class, 'connectFocusCredential'])
    ->middleware('throttle:5,1')
    ->name('fiscal.focus-credential.connect');
Route::delete('fiscal/focus-credential', [FiscalController::class, 'disconnectFocusCredential'])
    ->middleware('throttle:3,1')
    ->name('fiscal.focus-credential.disconnect');
Route::post('fiscal/production/activate', [FiscalController::class, 'activateProduction'])
    ->middleware('throttle:6,1')
    ->name('fiscal.production.activate');
Route::delete('fiscal/production', [FiscalController::class, 'deactivateProduction'])
    ->middleware('throttle:6,1')
    ->name('fiscal.production.deactivate');
Route::post('fiscal/homologation/reviews', [FiscalController::class, 'storeHomologationReview'])
    ->middleware('throttle:5,1')
    ->name('fiscal.homologation-reviews.store');
Route::get('fiscal/homologation/report', [FiscalController::class, 'homologationReport'])
    ->name('fiscal.homologation-report');
Route::get('fiscal/homologation/report.json', [FiscalController::class, 'homologationReportJson'])
    ->name('fiscal.homologation-report.json');
Route::get('fiscal/reconciliation', [FiscalReconciliationController::class, 'index'])
    ->name('fiscal.reconciliation.index');
Route::get('fiscal/reconciliation.csv', [FiscalReconciliationController::class, 'export'])
    ->name('fiscal.reconciliation.export');
Route::get('fiscal/reconciliation/closures', [FiscalReconciliationController::class, 'closureHistory'])
    ->name('fiscal.reconciliation.closures.index');
Route::post('fiscal/reconciliation/closures', [FiscalReconciliationController::class, 'storeClosure'])
    ->middleware('throttle:5,1')
    ->name('fiscal.reconciliation.closures.store');
Route::get('fiscal/reconciliation/closures/{closure}/dossier', [FiscalReconciliationController::class, 'closureDossier'])
    ->whereUlid('closure')
    ->name('fiscal.reconciliation.closures.dossier');
Route::get('fiscal/reconciliation/closures/{closure}/dossier.json', [FiscalReconciliationController::class, 'closureDossierJson'])
    ->whereUlid('closure')
    ->name('fiscal.reconciliation.closures.dossier.json');
Route::get('fiscal/returns/{sale}', [FiscalReturnReviewController::class, 'show'])
    ->whereNumber('sale')
    ->name('fiscal.returns.show');
Route::get('fiscal/returns/{sale}/reviews/{review}/dossier', [FiscalReturnReviewController::class, 'dossier'])
    ->whereNumber('sale')
    ->whereUlid('review')
    ->name('fiscal.returns.dossier');
Route::get('fiscal/returns/{sale}/reviews/{review}/dossier.json', [FiscalReturnReviewController::class, 'dossierJson'])
    ->whereNumber('sale')
    ->whereUlid('review')
    ->name('fiscal.returns.dossier.json');
Route::post('fiscal/returns/{sale}/reviews', [FiscalReturnReviewController::class, 'store'])
    ->whereNumber('sale')
    ->middleware('throttle:5,1')
    ->name('fiscal.returns.reviews.store');
Route::post('fiscal/sales/{sale}/prepare', [FiscalController::class, 'prepare'])
    ->whereNumber('sale')
    ->name('fiscal.prepare');
Route::post('fiscal/sales/{sale}/allocation/approve', [FiscalController::class, 'approveSaleAllocation'])
    ->whereNumber('sale')
    ->middleware('throttle:10,1')
    ->name('fiscal.sales.allocation.approve');
Route::get('fiscal/documents', [FiscalDocumentCenterController::class, 'index'])
    ->name('fiscal.documents.index');
Route::get('fiscal/documents.csv', [FiscalDocumentCenterController::class, 'export'])
    ->name('fiscal.documents.export');
Route::get('fiscal/documents/{document}', [FiscalController::class, 'show'])
    ->whereNumber('document')
    ->name('fiscal.documents.show');
Route::post('fiscal/documents/{document}/payload', [FiscalController::class, 'generatePayload'])
    ->whereNumber('document')
    ->name('fiscal.documents.payload');
Route::post('fiscal/documents/{document}/submit', [FiscalController::class, 'queueSubmission'])
    ->whereNumber('document')
    ->name('fiscal.documents.submit');
Route::post('fiscal/documents/{document}/synchronize', [FiscalController::class, 'queueSynchronization'])
    ->whereNumber('document')
    ->name('fiscal.documents.synchronize');
Route::post('fiscal/documents/{document}/cancel', [FiscalController::class, 'queueCancellation'])
    ->whereNumber('document')
    ->name('fiscal.documents.cancel');
Route::post('fiscal/nfce/inutilizations', [FiscalController::class, 'queueNfceInutilization'])
    ->middleware('throttle:6,1')
    ->name('fiscal.nfce.inutilizations.store');
Route::post('fiscal/documents/{document}/webhook-resend', [FiscalController::class, 'queueWebhookResend'])
    ->whereNumber('document')
    ->name('fiscal.documents.webhook-resend');
Route::get('fiscal/documents/{document}/artifacts/{artifact}', [FiscalController::class, 'downloadArtifact'])
    ->whereNumber('document')
    ->whereIn('artifact', ['xml', 'danfe', 'danfse'])
    ->name('fiscal.documents.artifact');

Route::get('fiscal/products', [FiscalProductController::class, 'index'])->name('fiscal.products.index');
Route::get('fiscal/products/{product}/edit', [FiscalProductController::class, 'edit'])
    ->whereNumber('product')
    ->name('fiscal.products.edit');
Route::put('fiscal/products/{product}', [FiscalProductController::class, 'update'])
    ->whereNumber('product')
    ->name('fiscal.products.update');

Route::get('fiscal/services', [FiscalServiceController::class, 'index'])->name('fiscal.services.index');
Route::get('fiscal/services/{service}/edit', [FiscalServiceController::class, 'edit'])
    ->whereNumber('service')
    ->name('fiscal.services.edit');
Route::put('fiscal/services/{service}', [FiscalServiceController::class, 'update'])
    ->whereNumber('service')
    ->name('fiscal.services.update');
