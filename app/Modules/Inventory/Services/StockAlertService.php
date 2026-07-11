<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Products\Models\Product;
use Illuminate\Support\Collection;

class StockAlertService
{
    public function __construct(private readonly ProductLotService $lotService)
    {
    }

    public function data(): array
    {
        $lotSummaries = $this->lotService->allLotSummaries();
        $untrackedProducts = $this->lotService->untrackedProducts();
        $lowStockProducts = $this->lowStockProducts();
        $withoutPriceProducts = $this->withoutPriceProducts();
        $withoutImageProducts = $this->withoutImageProducts();
        $expiredLots = $lotSummaries->where('status', 'expired')->values();
        $expiringLots = $lotSummaries->where('status', 'expiring')->values();

        return [
            'stats' => [
                'total' => $lowStockProducts->count()
                    + $expiredLots->count()
                    + $expiringLots->count()
                    + $untrackedProducts->count()
                    + $withoutPriceProducts->count()
                    + $withoutImageProducts->count(),
                'critical' => $expiredLots->count() + $lowStockProducts->count() + $withoutPriceProducts->count(),
                'attention' => $expiringLots->count() + $untrackedProducts->count(),
                'cadastro' => $withoutImageProducts->count(),
            ],
            'lowStockProducts' => $lowStockProducts,
            'expiredLots' => $expiredLots,
            'expiringLots' => $expiringLots,
            'untrackedProducts' => $untrackedProducts,
            'withoutPriceProducts' => $withoutPriceProducts,
            'withoutImageProducts' => $withoutImageProducts,
        ];
    }

    private function lowStockProducts(): Collection
    {
        return Product::query()
            ->active()
            ->where('minimum_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderBy('name')
            ->get();
    }

    private function withoutPriceProducts(): Collection
    {
        return Product::query()
            ->active()
            ->where(function ($query) {
                $query
                    ->whereNull('sale_price')
                    ->orWhere('sale_price', '<=', 0);
            })
            ->orderBy('name')
            ->get();
    }

    private function withoutImageProducts(): Collection
    {
        return Product::query()
            ->active()
            ->where(function ($query) {
                $query
                    ->whereNull('image_path')
                    ->orWhere('image_path', '');
            })
            ->orderBy('name')
            ->get();
    }
}
