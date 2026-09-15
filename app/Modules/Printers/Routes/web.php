<?php

use App\Modules\Printers\Controllers\PrinterController;
use Illuminate\Support\Facades\Route;

Route::get('settings/printers', [PrinterController::class, 'index'])->name('printers.index');
Route::get('settings/printers/create', [PrinterController::class, 'create'])->name('printers.create');
Route::post('settings/printers', [PrinterController::class, 'store'])->name('printers.store');
Route::get('settings/printers/{printer}/edit', [PrinterController::class, 'edit'])->whereNumber('printer')->name('printers.edit');
Route::put('settings/printers/{printer}', [PrinterController::class, 'update'])->whereNumber('printer')->name('printers.update');
Route::get('settings/printers/{printer}/test', [PrinterController::class, 'test'])->whereNumber('printer')->name('printers.test');
