<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Financial\Models\FinancialTransaction;
use App\Models\User;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Support\Gtin;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\PurchaseEntries\Models\PurchaseEntryItem;
use Illuminate\Support\Collection;

class PurchaseEntryInsightService
{
    public function __construct(
        private readonly ProductLookupService $lookupService,
        private readonly ReplenishmentSuggestionService $replenishmentSuggestions,
        private readonly ReplenishmentReviewService $replenishmentReviews,
    ) {}

    public function dashboard(): array
    {
        $allReplenishment = $this->replenishmentSuggestions->suggestions();
        $replenishment = $allReplenishment->take(8)->values();
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
                'replenishment_items' => $allReplenishment->count(),
                'estimated_replenishment_cost' => (float) $allReplenishment
                    ->sum(fn (array $item) => $item['estimated_cost']),
            ],
            'replenishment' => $replenishment,
            'topSuppliers' => $this->topSuppliers(),
            'recentCosts' => $this->recentCosts(),
        ];
    }

    public function replenishmentData(User $user): array
    {
        $items = $this->replenishmentReviews->attachLatest(
            $user,
            $this->replenishmentSuggestions->suggestions(),
        );

        return [
            'items' => $items,
            'stats' => [
                'products' => $items->count(),
                'estimated_cost' => (float) $items->sum(fn (array $item) => $item['estimated_cost']),
                'critical' => $items
                    ->where('priority', 'critical')
                    ->count(),
                'below_minimum' => $items
                    ->where('priority', '!=', 'critical')
                    ->count(),
                'history_based' => $items
                    ->where('uses_purchase_history', true)
                    ->count(),
                'without_history' => $items
                    ->where('history_count', 0)
                    ->count(),
                'with_recent_demand' => $items
                    ->where('has_recent_demand', true)
                    ->count(),
                'without_recent_demand' => $items
                    ->where('has_recent_demand', false)
                    ->count(),
                'with_supplier_lead_time' => $items
                    ->where('has_supplier_lead_time', true)
                    ->count(),
                'coverage_risk' => $items
                    ->whereIn('coverage_risk', ['critical', 'risk'])
                    ->count(),
                'coverage_unmeasured' => $items
                    ->whereIn('coverage_risk', ['insufficient', 'unmeasured'])
                    ->count(),
                'reviews_current' => $items
                    ->whereIn('review_status.key', ['reviewed', 'held'])
                    ->count(),
                'reviews_stale' => $items
                    ->where('review_status.key', 'stale')
                    ->count(),
                'reviews_pending' => $items
                    ->where('review_status.key', 'pending')
                    ->count(),
            ],
            'historyWindowDays' => ReplenishmentSuggestionService::HISTORY_WINDOW_DAYS,
            'demandWindowDays' => ProductDemandSignalService::WINDOW_DAYS,
        ];
    }

    public function lookupPayload(string $gtin, ?int $clinicId = null): array
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

        $productQuery = Product::query()
            ->with('globalProduct')
            ->active();

        if ($clinicId !== null) {
            $productQuery->where('clinic_id', $clinicId);
        }

        $product = $productQuery
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

        $outcome = $this->lookupService->lookupOutcome($normalized);
        $result = $outcome->result;
        $createUrl = route('products.create').'?'.http_build_query([
            'gtin' => $normalized,
            'clinic_id' => $clinicId,
            'from' => 'purchase',
            'return_to' => 'purchase',
        ]);

        if ($outcome->unavailable()) {
            return [
                'status' => 503,
                'payload' => [
                    'found' => false,
                    'manual_allowed' => true,
                    'lookup_status' => $outcome->status,
                    'retryable' => true,
                    'message' => 'Consulta externa indisponivel agora. Cadastre o produto manualmente para continuar.',
                    'product_create_url' => $createUrl,
                ],
            ];
        }

        if (! $result?->hasUsefulData()) {
            return [
                'status' => 200,
                'payload' => [
                    'found' => false,
                    'manual_allowed' => true,
                    'lookup_status' => $outcome->status,
                    'cached' => $outcome->cached,
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
        $suggestion = $this->replenishmentSuggestion($product);
        $suggestedQuantity = $suggestion['suggested_quantity'];
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
            'replenishment_confidence' => $suggestion['confidence'],
            'replenishment_history_count' => $suggestion['history_count'],
            'replenishment_reason' => $suggestion['reason'],
            'demand_window_days' => $suggestion['demand_window_days'],
            'net_demand_quantity' => $suggestion['net_demand_quantity'],
            'average_monthly_demand' => $suggestion['average_monthly_demand'],
            'reference_supplier_deliveries' => $suggestion['reference_supplier_deliveries'],
            'reference_supplier_average_lead_time_days' => $suggestion['reference_supplier_average_lead_time_days'],
            'coverage_days' => $suggestion['coverage_days'],
            'coverage_margin_days' => $suggestion['coverage_margin_days'],
            'projected_stock_at_receipt' => $suggestion['projected_stock_at_receipt'],
            'coverage_risk' => $suggestion['coverage_risk'],
            'coverage_risk_label' => $suggestion['coverage_risk_label'],
        ];
    }

    public function suggestedQuantity(Product $product): float
    {
        return (float) $this->replenishmentSuggestion($product)['suggested_quantity'];
    }

    public function replenishmentSuggestion(Product $product): array
    {
        return $this->replenishmentSuggestions->suggestionFor($product);
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
