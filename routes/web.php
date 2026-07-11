<?php

use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Services\GlobalProductCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/assets/app.css', function () {
    return response()->file(resource_path('css/app.css'), [
        'Content-Type' => 'text/css; charset=UTF-8',
    ]);
})->name('assets.css');

Route::get('/assets/app.js', function () {
    return response()->file(resource_path('js/app.js'), [
        'Content-Type' => 'application/javascript; charset=UTF-8',
    ]);
})->name('assets.js');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/global-products', function (Request $request, GlobalProductCatalogService $catalog) {
    return view('products.global-catalog', $catalog->indexData($request));
})->name('global-products.index');

Route::patch('/global-products/{globalProduct}/status', function (
    Request $request,
    GlobalProduct $globalProduct,
    GlobalProductCatalogService $catalog
) {
    $validated = $request->validate([
        'status' => ['required', 'in:'.implode(',', array_keys($catalog->statuses()))],
    ]);

    $catalog->updateStatus($globalProduct, $validated['status']);

    return back()
        ->with('success', 'Status do produto global atualizado.');
})->name('global-products.status');

$moduleRoutes = [
    app_path('Modules/Appointments/Routes/web.php'),
    app_path('Modules/Clinics/Routes/web.php'),
    app_path('Modules/Financial/Routes/web.php'),
    app_path('Modules/Inventory/Routes/web.php'),
    app_path('Modules/Patients/Routes/web.php'),
    app_path('Modules/PetShopServices/Routes/web.php'),
    app_path('Modules/Products/Routes/web.php'),
    app_path('Modules/PurchaseEntries/Routes/web.php'),
    app_path('Modules/Sales/Routes/web.php'),
    app_path('Modules/Schedules/Routes/web.php'),
    app_path('Modules/ServiceOrders/Routes/web.php'),
    app_path('Modules/Suppliers/Routes/web.php'),
    app_path('Modules/Tutors/Routes/web.php'),
];

foreach ($moduleRoutes as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}
