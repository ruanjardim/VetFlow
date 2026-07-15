<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Support\Gtin;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\PurchaseEntries\Models\PurchaseEntryItem;
use Illuminate\Support\Collection;

class PurchaseEntryInsightService
{
    public function __construct(private readonly ProductLookupService $lookupService)
    {
    }

    public function dashboard(): array
    {
        $replenishment = $this->replenishmentProducts(8);
        $monthTotal = PurchaseEntry::query()
            ->where('status', 'received')
            ->whereBetween('purchased_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');
        $pendingPayables = FinancialTransaction::query()
            ->where('type', 'expense')
            ->where('status', 'pending')
            ->sum('amount');
        $overduePayables = FinancialTransaction::query()
            ->where('type', 'expense')
            ->where('status', 'pending')
            ->whereDate('due_date', '<', today())
            ->sum('amount');

        return [
            'stats' => [
                'entries_month' => PurchaseEntry::query()
                    ->whereBetween('purchased_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'received_month' => PurchaseEntry::query()
                    ->where('status', 'received')
                    ->whereBetween('purchased_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'drafts' => PurchaseEntry::query()->where('status', 'draft')->count(),
                'month_total' => (float) $monthTotal,
                'pending_payables' => (float) $pendingPayables,
                'overdue_payables' => (float) $overduePayables,
                'replenishment_items' => $this->replenishmentProducts()->count(),
                'estimated_replenishment_cost' => (float) $this->replenishmentProducts()
                    ->sum(fn (array $item) => $item['estimated_cost']),
            ],
            'replenishment' => $replenishment,
            'topSuppliers' => $this->topSuppliers(),
            'recentCosts' => $this->recentCosts(),
        ];
    }

    public function replenishmentData(): array
    {
        return [
            'items' => $this->replenishmentProducts(),
            'stats' => [
                'products' => $this->replenishmentProducts()->count(),
                'estimated_cost' => (float) $this->replenishmentProducts()->sum(fn (array $item) => $item['estimated_cost']),
                'critical' => $this->replenishmentProducts()
                    ->filter(fn (array $item) => (float) $item['stock_quantity'] <= 0)
                    ->count(),
                'below_minimum' => $this->replenishmentProducts()
                    ->filter(fn (array $item) => (float) $item['stock_quantity'] > 0)
                    ->count(),
            ],
        ];
    }

    public function lookupPayload(string $gtin): array
    {
        $normalized = Gtin::normalize($gtin);
        $variants = Gtin::variants($normalized);

        if (! Gtin::looksValid($normalized)) {
            return [
                'status' => 422,
                'payload' => [
                    'found' => false,
                    'manual_allowed' => true,
                    'message' => 'Codigo de barras invalido. Informe outro EAN/GTIN.',
                ],
            ];
        }

        $product = Product::query()
            ->with('globalProduct')
            ->active()
            ->where(function ($query) use ($variants) {
                $query
                    ->whereIn('gtin', $variants)
                    ->orWhereIn('barcode', $variants);
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($product) {
            return [
                'status' => 200,
                'payload' => [
                    'found' => true,
                    'mode' => 'product',
                    'manual_allowed' => false,
                    'message' => 'Produto encontrado para entrada de mercadorias.',
                    'product_edit_url' => route('products.edit', $product->id),
                    'item' => $this->purchaseItemPayload($product, $normalized),
                    'warnings' => $this->warnings($product),
                ],
            ];
        }

        $result = $this->lookupService->lookup($normalized);
        $createUrl = route('products.create').'?'.http_build_query([
            'gtin' => $normalized,
            'from' => 'purchase',
            'return_to' => 'purchase',
        ]);

        if (! $result?->hasUsefulData()) {
            return [
                'status' => 200,
                'payload' => [
                    'found' => false,
                    'manual_allowed' => true,
                    'message' => 'Produto nao cadastrado. Cadastre este EAN antes de lancar a compra.',
                    'product_create_url' => $createUrl,
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'found' => true,
                'mode' => 'catalog',
                'manual_allowed' => true,
                'message' => 'Produto reconhecido no Catalogo Global. Cadastre antes de lancar a compra.',
                'source' => $result->source,
                'product_create_url' => $createUrl,
                'product' => array_filter(
                    $result->toProductAttributes(),
                    fn ($value) => $value !== null && $value !== ''
                ),
            ],
        ];
    }

    public function purchaseItemPayload(Product $product, ?string $fallbackGtin = null): array
    {
        $suggestedQuantity = $this->suggestedQuantity($product);
        $unitCost = (float) $product->cost_price;
        $salePrice = (float) $product->sale_price;

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'description' => $product->name,
            'sku' => $product->sku,
            'gtin' => $product->gtin ?: $fallbackGtin,
            'barcode' => $product->barcode ?: $fallbackGtin,
            'unit' => $product->unit ?: 'un',
            'cost_price' => $unitCost,
            'sale_price' => $salePrice,
            'stock_quantity' => (float) $product->stock_quantity,
            'minimum_stock' => (float) $product->minimum_stock,
            'suggested_quantity' => $suggestedQuantity,
            'suggested_sale_price' => $this->suggestedSalePrice($unitCost, $salePrice),
            'margin_percent' => $this->marginPercent($unitCost, $salePrice),
            'global_product_id' => $product->global_product_id,
            'global_status' => $product->globalProduct?->status,
        ];
    }

    public function suggestedQuantity(Product $product): float
    {
        $minimum = (float) $product->minimum_stock;
        $stock = (float) $product->stock_quantity;

        if ($minimum <= 0) {
            return 1;
        }

        return max(1, round(($minimum * 2) - $stock, 3));
    }

    public function suggestedSalePrice(float $unitCost, float $currentSalePrice = 0): float
    {
        if ($currentSalePrice > 0) {
            return $currentSalePrice;
        }

        if ($unitCost <= 0) {
            return 0;
        }

        return round($unitCost * 1.45, 2);
    }

    public function marginPercent(float $unitCost, float $salePrice): ?float
    {
        if ($salePrice <= 0) {
            return null;
        }

        return round((($salePrice - $unitCost) / $salePrice) * 100, 2);
    }

    private function replenishmentProducts(?int $limit = null): Collection
    {
        $query = Product::query()
            ->with('globalProduct')
            ->active()
            ->where('minimum_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderBy('stock_quantity')
            ->orderBy('name');

        if ($limit) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(function (Product $product) {
                $suggestedQuantity = $this->suggestedQuantity($product);
                $unitCost = (float) $product->cost_price;

                return [
                    'product' => $product,
                    'stock_quantity' => (float) $product->stock_quantity,
                    'minimum_stock' => (float) $product->minimum_stock,
                    'suggested_quantity' => $suggestedQuantity,
                    'unit' => $product->unit ?: 'un',
                    'unit_cost' => $unitCost,
                    'estimated_cost' => round($suggestedQuantity * $unitCost, 2),
                    'scan_url' => route('purchase-entries.create', [
                        'scan' => $product->gtin ?: $product->barcode,
                    ]),
                ];
            });
    }

    private function topSuppliers(): Collection
    {
        return PurchaseEntry::query()
            ->with('supplier')
            ->selectRaw('supplier_id, COUNT(*) as entries_count, SUM(total) as entries_total')
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->orderByDesc('entries_total')
            ->limit(5)
            ->get()
            ->map(fn (PurchaseEntry $entry) => [
                'supplier' => $entry->supplier?->name ?: 'Fornecedor',
                'entries_count' => (int) $entry->entries_count,
                'entries_total' => (float) $entry->entries_total,
            ]);
    }

    private function recentCosts(): Collection
    {
        return PurchaseEntryItem::query()
            ->with('product')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (PurchaseEntryItem $item) => [
                'product' => $item->product?->name ?: $item->description,
                'unit_cost' => (float) $item->unit_cost,
                'sale_price' => (float) $item->sale_price,
                'margin_percent' => $item->margin_percent !== null ? (float) $item->margin_percent : null,
                'created_at' => $item->created_at,
            ]);
    }

    private function warnings(Product $product): array
    {
        return array_values(array_filter([
            (float) $product->sale_price <= 0 ? 'Produto sem preco de venda.' : null,
            (float) $product->minimum_stock <= 0 ? 'Estoque minimo ainda nao definido.' : null,
            ! $product->global_product_id ? 'Produto ainda sem vinculo global.' : null,
        ]));
    }
}
