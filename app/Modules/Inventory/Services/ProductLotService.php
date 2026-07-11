<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductLotService
{
    public function allLotSummaries(): Collection
    {
        return $this->summariesFromQuery(
            InventoryMovement::query()
                ->with('product')
                ->whereNotNull('lot_number')
        );
    }

    public function untrackedProducts(): Collection
    {
        return Product::query()
            ->active()
            ->where('stock_quantity', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $trackedQuantity = $this->lotBalancesForProduct((int) $product->id)
                    ->sum('quantity');
                $untrackedQuantity = max(0, (float) $product->stock_quantity - (float) $trackedQuantity);

                return [
                    'product' => $product,
                    'quantity' => $untrackedQuantity,
                    'unit' => $product->unit ?: 'un',
                ];
            })
            ->filter(fn (array $item) => (float) $item['quantity'] > 0)
            ->values();
    }

    public function lotBalancesForProduct(int $productId): Collection
    {
        return $this->summariesFromQuery(
            InventoryMovement::query()
                ->with('product')
                ->where('product_id', $productId)
                ->whereNotNull('lot_number')
        );
    }

    public function sellableQuantity(Product $product): float
    {
        $expiredQuantity = $this->lotBalancesForProduct((int) $product->id)
            ->where('status', 'expired')
            ->sum('quantity');

        return max(0, (float) $product->stock_quantity - (float) $expiredQuantity);
    }

    public function allocateForSale(Product $product, float $quantity): array
    {
        $remaining = max(0, $quantity);

        if ($remaining <= 0) {
            return [];
        }

        $lotBalances = $this->lotBalancesForProduct((int) $product->id);
        $sellableLots = $lotBalances
            ->reject(fn (array $lot) => $lot['status'] === 'expired')
            ->sortBy(fn (array $lot) => sprintf(
                '%d|%012d|%s',
                $lot['status'] === 'without_expiration' ? 1 : 0,
                optional($lot['expires_at'])->timestamp ?? PHP_INT_MAX,
                $lot['lot_number']
            ))
            ->values();

        $allocations = [];

        foreach ($sellableLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $lot['quantity']);

            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'quantity' => $take,
                'lot_number' => $lot['lot_number'],
                'expires_at' => $lot['expires_at'],
            ];

            $remaining = round($remaining - $take, 3);
        }

        $untrackedSellable = max(0, $this->sellableQuantity($product) - (float) $sellableLots->sum('quantity'));

        if ($remaining > 0 && $untrackedSellable > 0) {
            $take = min($remaining, $untrackedSellable);

            $allocations[] = [
                'quantity' => $take,
                'lot_number' => null,
                'expires_at' => null,
            ];
        }

        return $allocations;
    }

    private function summariesFromQuery(Builder $query): Collection
    {
        $today = today();
        $warningLimit = today()->addDays(30);

        return $query
            ->orderBy('expires_at')
            ->latest('occurred_at')
            ->get()
            ->groupBy(fn (InventoryMovement $movement) => implode('|', [
                $movement->product_id,
                trim((string) $movement->lot_number),
                optional($movement->expires_at)->format('Y-m-d') ?: 'sem-validade',
            ]))
            ->map(function (Collection $movements) use ($today, $warningLimit) {
                /** @var InventoryMovement $first */
                $first = $movements->sortByDesc('occurred_at')->first();
                $quantity = $movements->sum(fn (InventoryMovement $movement) => $this->lotQuantityEffect($movement));
                $expiresAt = $first->expires_at;
                $status = 'ok';

                if (! $expiresAt) {
                    $status = 'without_expiration';
                } elseif ($expiresAt->lt($today)) {
                    $status = 'expired';
                } elseif ($expiresAt->lte($warningLimit)) {
                    $status = 'expiring';
                }

                return [
                    'product' => $first->product,
                    'product_id' => $first->product_id,
                    'lot_number' => $first->lot_number,
                    'expires_at' => $expiresAt,
                    'quantity' => $quantity,
                    'unit' => $first->product?->unit ?: 'un',
                    'status' => $status,
                    'last_movement_at' => $first->occurred_at,
                ];
            })
            ->filter(fn (array $lot) => (float) $lot['quantity'] > 0)
            ->sortBy(fn (array $lot) => sprintf(
                '%d|%012d|%s',
                match ($lot['status']) {
                    'expired' => 0,
                    'expiring' => 1,
                    'without_expiration' => 3,
                    default => 2,
                },
                optional($lot['expires_at'])->timestamp ?? PHP_INT_MAX,
                $lot['product']?->name ?? ''
            ))
            ->values();
    }

    private function lotQuantityEffect(InventoryMovement $movement): float
    {
        $quantity = (float) $movement->quantity;

        return $movement->type === 'exit' ? -1 * $quantity : $quantity;
    }
}
