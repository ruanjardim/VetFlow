<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardDataService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardDataService $dashboardDataService
    ) {
    }

    public function index()
    {
        return view(
            'dashboard.index',
            $this->dashboardDataService->get()
        );
    }
}