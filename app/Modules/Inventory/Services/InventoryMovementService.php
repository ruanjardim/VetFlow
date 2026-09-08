<?php

namespace App\Modules\Inventory\Services;

use App\Core\Base\BaseService;
use App\Modules\Inventory\Contracts\InventoryMovementRepositoryInterface;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryMovementService extends BaseService
{
    public function __construct(InventoryMovementRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $data['occurred_at'] = $data['occurred_at'] ?? now();
            $data['source'] = $data['source'] ?? 'manual';
            $data['balance_before'] = (float) Product::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['product_id'])
                ->stock_quantity;

            /** @var InventoryMovement $movement */
            $movement = $this->repository->create($data);
            $balanceAfter = $this->applyStockEffect($movement);

            $movement->update(['balance_after' => $balanceAfter]);

            return $movement->refresh();
        });
    }

    public function update(int $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            /** @var InventoryMovement $movement */
            $movement = $this->repository->findOrFail($id);

            $this->reverseStockEffect($movement);
            $data['balance_before'] = $this->currentProductStock((int) ($data['product_id'] ?? $movement->product_id));
            $data['source'] = $data['source'] ?? $movement->source ?? 'manual';
            $this->repository->update($movement, $data);

            $movement->refresh();

            $balanceAfter = $this->applyStockEffect($movement);
            $movement->update(['balance_after' => $balanceAfter]);

            return $movement->refresh();
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var InventoryMovement $movement */
            $movement = $this->repository->findOrFail($id);

            $this->reverseStockEffect($movement);

            return (bool) $this->repository->delete($movement);
        });
    }

    private function applyStockEffect(InventoryMovement $movement): float
    {
        $product = $this->product($movement);
        $effect = $this->stockEffect($movement);

        $this->moveStock($product, $effect);

        return (float) $product->refresh()->stock_quantity;
    }

    private function reverseStockEffect(InventoryMovement $movement): float
    {
        $product = $this->product($movement);
        $effect = -1 * $this->stockEffect($movement);

        $this->moveStock($product, $effect);

        return (float) $product->refresh()->stock_quantity;
    }

    private function stockEffect(InventoryMovement $movement): float
    {
        $quantity = (float) $movement->quantity;

        if ($movement->type === 'lot_assignment') {
            return 0;
        }

        return $movement->type === 'exit'
            ? -1 * $quantity
            : $quantity;
    }

    private function moveStock(Product $product, float $effect): void
    {
        if ($effect >= 0) {
            $product->increment('stock_quantity', $effect);

            return;
        }

        $product->decrement('stock_quantity', abs($effect));
    }

    private function product(InventoryMovement $movement): Product
    {
        return Product::query()->findOrFail($movement->product_id);
    }

    private function currentProductStock(int $productId): float
    {
        return (float) Product::query()->whereKey($productId)->value('stock_quantity');
    }
}
