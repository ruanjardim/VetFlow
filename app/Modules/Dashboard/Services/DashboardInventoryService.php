<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Products\Models\Product;

class DashboardInventoryService
{
    public function products(): int
    {
        return Product::count();
    }

    public function lowStock(): int
    {
        return Product::query()
            ->lowStock()
            ->count();
    }

    public function stockValue(): float
    {
        return (float) Product::query()
            ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as total')
            ->value('total');
    }
}
