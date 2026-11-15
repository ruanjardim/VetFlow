<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Support\Collection;

class ProductDemandSignalService
{
    public const WINDOW_DAYS = 90;

    /**
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, array<string, mixed>>
     */
    public function signalsForProducts(Collection $productIds): Collection
    {
        $productIds = $productIds
            ->map(fn ($productId): int => (int) $productId)
            ->filter(fn (int $productId): bool => $productId > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $cutoff = now()->subDays(self::WINDOW_DAYS)->startOfDay();
        $until = now()->endOfDay();

        return SaleItem::query()
            ->with('sale')
            ->where('type', 'product')
            ->whereIn('product_id', $productIds)
            ->whereHas('sale', function ($query) use ($cutoff, $until): void {
                $query
                    ->whereIn('status', ['completed', 'returned'])
                    ->where(function ($dateQuery) use ($cutoff, $until): void {
                        $dateQuery
                            ->whereBetween('completed_at', [$cutoff, $until])
                            ->orWhere(function ($fallbackQuery) use ($cutoff, $until): void {
                                $fallbackQuery
                                    ->whereNull('completed_at')
                                    ->whereBetween('sold_at', [$cutoff, $until]);
                            });
                    });
            })
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $items): array => $this->summarize($items));
    }

    /** @return array<string, mixed> */
    public function signalForProduct(Product $product): array
    {
        return $this->signalsForProducts(collect([$product->id]))
            ->get($product->id, $this->emptySignal());
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     * @return array<string, mixed>
     */
    private function summarize(Collection $items): array
    {
        $soldQuantity = round((float) $items->sum(
            fn (SaleItem $item): float => max(0, (float) $item->quantity)
        ), 3);
        $returnedQuantity = round((float) $items->sum(function (SaleItem $item): float {
            return min(
                max(0, (float) $item->quantity),
                max(0, (float) $item->returned_quantity),
            );
        }), 3);
        $netQuantity = round(max(0, $soldQuantity - $returnedQuantity), 3);
        $lastSaleAt = $items
            ->map(fn (SaleItem $item) => $item->sale?->completed_at ?? $item->sale?->sold_at)
            ->filter()
            ->sortByDesc(fn ($date) => $date->getTimestamp())
            ->first();

        return [
            'window_days' => self::WINDOW_DAYS,
            'sales_count' => $items->pluck('sale_id')->unique()->count(),
            'sold_quantity' => $soldQuantity,
            'returned_quantity' => $returnedQuantity,
            'net_quantity' => $netQuantity,
            'average_monthly_quantity' => round($netQuantity / self::WINDOW_DAYS * 30, 3),
            'last_sale_at' => $lastSaleAt,
            'has_recent_demand' => $netQuantity > 0,
        ];
    }

    /** @return array<string, mixed> */
    public function emptySignal(): array
    {
        return [
            'window_days' => self::WINDOW_DAYS,
            'sales_count' => 0,
            'sold_quantity' => 0.0,
            'returned_quantity' => 0.0,
            'net_quantity' => 0.0,
            'average_monthly_quantity' => 0.0,
            'last_sale_at' => null,
            'has_recent_demand' => false,
        ];
    }
}
