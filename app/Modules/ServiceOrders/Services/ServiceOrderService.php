<?php

namespace App\Modules\ServiceOrders\Services;

use App\Core\Base\BaseService;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\ServiceOrders\Contracts\ServiceOrderRepositoryInterface;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ServiceOrderService extends BaseService
{
    public function __construct(ServiceOrderRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['code'] = $this->nextCode();
            $data['opened_at'] = $data['opened_at'] ?? now();

            /** @var ServiceOrder $order */
            $order = $this->repository->create($data);

            $this->syncItems($order, $items);
            $this->recalculateTotals($order);

            return $order->refresh();
        });
    }

    public function update(int $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            /** @var ServiceOrder $order */
            $order = $this->repository->findOrFail($id);

            $this->repository->update($order, $data);
            $order->items()->delete();

            $this->syncItems($order->refresh(), $items);
            $this->recalculateTotals($order);

            return $order->refresh();
        });
    }

    private function syncItems(ServiceOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $normalized = $this->normalizeItem($item);

            if ($normalized === null) {
                continue;
            }

            $order->items()->create($normalized);
        }
    }

    private function normalizeItem(array $item): ?array
    {
        $type = $item['type'] ?? 'service';
        $quantity = (float) ($item['quantity'] ?? 0);

        if ($quantity <= 0) {
            return null;
        }

        $productId = $type === 'product' ? ($item['product_id'] ?? null) : null;
        $serviceId = $type === 'service' ? ($item['petshop_service_id'] ?? null) : null;
        $description = trim((string) ($item['description'] ?? ''));
        $unitPrice = $item['unit_price'] ?? null;

        if ($type === 'product' && $productId) {
            $product = Product::query()->find($productId);
            $description = $description ?: (string) $product?->name;
            $unitPrice = $unitPrice !== null && $unitPrice !== ''
                ? (float) $unitPrice
                : (float) ($product?->sale_price ?? 0);
        }

        if ($type === 'service' && $serviceId) {
            $service = PetShopService::query()->find($serviceId);
            $description = $description ?: (string) $service?->name;
            $unitPrice = $unitPrice !== null && $unitPrice !== ''
                ? (float) $unitPrice
                : (float) ($service?->base_price ?? 0);
        }

        if ($description === '') {
            return null;
        }

        $unitPrice = (float) ($unitPrice ?: 0);

        return [
            'type' => $type,
            'product_id' => $productId,
            'petshop_service_id' => $serviceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ];
    }

    private function recalculateTotals(ServiceOrder $order): void
    {
        $order->load('items');

        $servicesTotal = (float) $order->items
            ->where('type', 'service')
            ->sum('total');

        $productsTotal = (float) $order->items
            ->where('type', 'product')
            ->sum('total');

        $customTotal = (float) $order->items
            ->where('type', 'custom')
            ->sum('total');

        $discount = (float) ($order->discount_total ?? 0);

        $order->update([
            'services_total' => $servicesTotal + $customTotal,
            'products_total' => $productsTotal,
            'total' => max(0, $servicesTotal + $productsTotal + $customTotal - $discount),
        ]);
    }

    private function nextCode(): string
    {
        $nextId = ((int) ServiceOrder::withTrashed()->max('id')) + 1;

        return 'CMD-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}
