<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Inventory\Requests\StoreInventoryMovementRequest;
use App\Modules\Inventory\Requests\UpdateInventoryMovementRequest;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Inventory\Services\ProductLotService;
use App\Modules\Inventory\Services\StockAlertService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Support\Gtin;
use Illuminate\Http\JsonResponse;

class InventoryMovementController extends BaseCrudController
{
    public function __construct(
        InventoryMovementService $service,
        private readonly ProductLotService $lotService
    ) {
        $this->service = $service;
        $this->viewPath = 'inventory-movements';
        $this->routeName = 'inventory-movements';
        $this->viewVariable = 'inventoryMovements';
    }

    public function index()
    {
        $lotSummaries = $this->lotService->allLotSummaries();
        $untrackedProducts = $this->lotService->untrackedProducts();

        return view("{$this->viewPath}.index", [
            $this->viewVariable => $this->service->paginate(),
            'lotSummaries' => $lotSummaries,
            'untrackedProducts' => $untrackedProducts,
            'lotStats' => [
                'total' => $lotSummaries->count(),
                'expired' => $lotSummaries->where('status', 'expired')->count(),
                'expiring' => $lotSummaries->where('status', 'expiring')->count(),
                'without_expiration' => $lotSummaries->where('status', 'without_expiration')->count(),
                'untracked' => $untrackedProducts->count(),
            ],
        ]);
    }

    public function alerts(StockAlertService $alertService)
    {
        return view("{$this->viewPath}.alerts", $alertService->data());
    }

    public function create()
    {
        return view("{$this->viewPath}.create", [
            'products' => $this->products(),
        ]);
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", [
            'item' => $this->service->findOrFail($id),
            'products' => $this->products(),
        ]);
    }

    public function lookupProduct(string $gtin, ProductLookupService $lookupService): JsonResponse
    {
        $normalized = Gtin::normalize($gtin);
        $variants = Gtin::variants($normalized);

        if (! Gtin::looksValid($normalized)) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'message' => 'Codigo de barras invalido. Informe outro EAN/GTIN.',
            ], 422);
        }

        $product = Product::query()
            ->active()
            ->where(function ($query) use ($variants) {
                $query
                    ->whereIn('gtin', $variants)
                    ->orWhereIn('barcode', $variants);
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($product) {
            return response()->json([
                'found' => true,
                'mode' => 'product',
                'manual_allowed' => false,
                'message' => 'Produto encontrado para movimentar estoque.',
                'product_edit_url' => route('products.edit', $product->id),
                'item' => [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'gtin' => $product->gtin ?: $normalized,
                    'barcode' => $product->barcode ?: $normalized,
                    'unit' => $product->unit,
                    'cost_price' => (float) $product->cost_price,
                    'sale_price' => (float) $product->sale_price,
                    'stock_quantity' => (float) $product->stock_quantity,
                    'minimum_stock' => (float) $product->minimum_stock,
                ],
            ]);
        }

        $outcome = $lookupService->lookupOutcome($normalized);
        $result = $outcome->result;
        $createUrl = route('products.create').'?'.http_build_query([
            'gtin' => $normalized,
            'from' => 'inventory',
        ]);

        if ($outcome->unavailable()) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'lookup_status' => $outcome->status,
                'retryable' => true,
                'message' => 'Consulta externa indisponivel agora. Cadastre o produto manualmente para continuar.',
                'product_create_url' => $createUrl,
            ], 503);
        }

        if (! $result?->hasUsefulData()) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'lookup_status' => $outcome->status,
                'cached' => $outcome->cached,
                'message' => 'Produto nao cadastrado. Cadastre este EAN antes de movimentar estoque.',
                'product_create_url' => $createUrl,
            ]);
        }

        return response()->json([
            'found' => true,
            'mode' => 'catalog',
            'manual_allowed' => true,
            'message' => 'Produto reconhecido no catalogo. Cadastre para movimentar estoque.',
            'source' => $result->source,
            'product_create_url' => $createUrl,
            'product' => array_filter(
                $result->toProductAttributes(),
                fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }

    protected function storeRequest(): string
    {
        return StoreInventoryMovementRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateInventoryMovementRequest::class;
    }

    private function products()
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->get();
    }
}
