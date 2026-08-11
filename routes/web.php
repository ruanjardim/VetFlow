<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Operations\QueueCronController;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\ProductIntelligence\Controllers\GlobalProductController;
use App\Modules\ProductIntelligence\Controllers\ProductIntelligenceApiController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/app.css', function () {
    return response()->file(resource_path('css/app.css'), [
        'Content-Type' => 'text/css; charset=UTF-8',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('assets.css');

Route::get('/assets/app.js', function () {
    return response()->file(resource_path('js/app.js'), [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('assets.js');

Route::get('/ops/cron/queue', QueueCronController::class)
    ->middleware('throttle:6,1')
    ->name('operations.queue-cron');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware(EnsureUserHasPermission::class.':dashboard.view')
        ->name('dashboard');

    Route::middleware(EnsureUserHasPermission::class.':global-products.manage')->group(function () {
        Route::get('/global-products', [GlobalProductController::class, 'index'])->name('global-products.index');
        Route::get('/global-products/export', [GlobalProductController::class, 'export'])->name('global-products.export');
        Route::get('/global-products/suggestions', [GlobalProductController::class, 'suggestions'])->name('global-products.suggestions');
        Route::patch('/global-products/suggestions/{suggestion}', [GlobalProductController::class, 'reviewSuggestion'])
            ->name('global-products.suggestions.review');
        Route::get('/global-products/{globalProduct}', [GlobalProductController::class, 'show'])
            ->whereNumber('globalProduct')
            ->name('global-products.show');
        Route::patch('/global-products/{globalProduct}/status', [GlobalProductController::class, 'updateStatus'])
            ->whereNumber('globalProduct')
            ->name('global-products.status');
        Route::post('/global-products/{globalProduct}/enrich', [GlobalProductController::class, 'enrich'])
            ->whereNumber('globalProduct')
            ->name('global-products.enrich');
        Route::post('/global-products/{globalProduct}/promote', [GlobalProductController::class, 'promote'])
            ->whereNumber('globalProduct')
            ->name('global-products.promote');
        Route::post('/global-products/{globalProduct}/sync-local', [GlobalProductController::class, 'syncLocal'])
            ->whereNumber('globalProduct')
            ->name('global-products.sync-local');

        Route::prefix('/api/product-intelligence')->name('product-intelligence.api.')->group(function () {
            Route::get('/metrics', [ProductIntelligenceApiController::class, 'metrics'])->name('metrics');
            Route::get('/lookup/{gtin}', [ProductIntelligenceApiController::class, 'lookup'])
                ->where('gtin', '[0-9A-Za-z-]+')
                ->name('lookup');
            Route::get('/global-products/{globalProduct}', [ProductIntelligenceApiController::class, 'globalProduct'])
                ->whereNumber('globalProduct')
                ->name('global-products.show');
        });
    });

    $moduleRoutes = [
        'appointments.manage' => app_path('Modules/Appointments/Routes/web.php'),
        'clinics.manage' => app_path('Modules/Clinics/Routes/web.php'),
        'users.manage' => app_path('Modules/Access/Routes/web.php'),
        'implementation.manage' => app_path('Modules/Implementation/Routes/web.php'),
        'financial.manage' => app_path('Modules/Financial/Routes/web.php'),
        'inventory.manage' => app_path('Modules/Inventory/Routes/web.php'),
        'medical-records.manage' => app_path('Modules/MedicalRecords/Routes/web.php'),
        'patients.manage' => app_path('Modules/Patients/Routes/web.php'),
        'petshop-services.manage' => app_path('Modules/PetShopServices/Routes/web.php'),
        'products.manage' => app_path('Modules/Products/Routes/web.php'),
        'purchase-entries.manage' => app_path('Modules/PurchaseEntries/Routes/web.php'),
        'sales.manage' => app_path('Modules/Sales/Routes/web.php'),
        'schedules.manage' => app_path('Modules/Schedules/Routes/web.php'),
        'service-orders.manage' => app_path('Modules/ServiceOrders/Routes/web.php'),
        'suppliers.manage' => app_path('Modules/Suppliers/Routes/web.php'),
        'tutors.manage' => app_path('Modules/Tutors/Routes/web.php'),
    ];

    foreach ($moduleRoutes as $permission => $routeFile) {
        Route::middleware(EnsureUserHasPermission::class.':'.$permission)->group(function () use ($routeFile) {
            if (file_exists($routeFile)) {
                require $routeFile;
            }
        });
    }
});
