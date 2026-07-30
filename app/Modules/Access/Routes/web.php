<?php

use App\Modules\Access\Controllers\AccessUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('access/users')
    ->name('access-users.')
    ->group(function (): void {
        Route::get('/', [AccessUserController::class, 'index'])->name('index');
        Route::get('/create', [AccessUserController::class, 'create'])->name('create');
        Route::post('/', [AccessUserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [AccessUserController::class, 'edit'])
            ->whereNumber('user')
            ->name('edit');
        Route::put('/{user}', [AccessUserController::class, 'update'])
            ->whereNumber('user')
            ->name('update');
    });
