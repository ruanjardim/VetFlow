<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntryItem;
use Illuminate\Support\Collection;

class ReplenishmentSuggestionService
{
    public const HISTORY_WINDOW_DAYS = 180;

    public function __construct(
        private readonly ProductDemandSignalService $demandSignals,
        private readonly SupplierProductSignalService $supplierSignals,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function suggestions(?int $limit = null): Collection
    {
        $products = Product::query()
            ->with('globalProduct')
            ->active()
            ->where('minimum_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->get();

        $historyByProduct = $this->historyForProducts($products->pluck('id'));
        $demandByProduct = $this->demandSignals->signalsForProducts($products->pluck('id'));

        $suggestions = $products
            ->map(fn (Product $product): array => $this->buildSuggestion(
                $product,
                $historyByProduct->get($product->id, collect()),
                $demandByProduct->get($product->id, $this->demandSignals->emptySignal()),
            ))
            ->sort(function (array $left, array $right): int {
                $priority = $left['priority_rank'] <=> $right['priority_rank'];

                if ($priority !== 0) {
                    return $priority;
                }

                $stockRatio = $left['stock_ratio'] <=> $right['stock_ratio'];

                if ($stockRatio !== 0) {
                    return $stockRatio;
                }

                return strnatcasecmp($left['product']->name, $right['product']->name);
            })
            ->values();

        return $limit === null ? $suggestions : $suggestions->take($limit)->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestionFor(Product $product): array
    {
        $history = $this->historyForProducts(collect([$product->id]))
            ->get($product->id, collect());
        $demand = $this->demandSignals->signalForProduct($product);

        return $this->buildSuggestion($product, $history, $demand);
    }

    public function baselineQuantity(Product $product): float
    {
        $minimum = (float) $product->minimum_stock;
        $stock = (float) $product->stock_quantity;

        if ($minimum <= 0) {
            return 1;
        }

        return max(1, round(($minimum * 2) - $stock, 3));
    }

    /**
     * @param  Collection<int, PurchaseEntryItem>  $history
     * @return array<string, mixed>
     */
    private function buildSuggestion(Product $product, Collection $history, array $demand): array
    {
        $batches = $this->purchaseBatches($history);
        $historyCount = $batches->count();
        $averagePurchaseQuantity = $historyCount > 0
            ? round((float) $batches->avg('quantity'), 3)
            : null;
        $baselineQuantity = $this->baselineQuantity($product);
        $usesPurchaseHistory = $historyCount >= 2
            && $averagePurchaseQuantity !== null
            && $averagePurchaseQuantity > $baselineQuantity;
        $suggestedQuantity = $usesPurchaseHistory
            ? $averagePurchaseQuantity
            : $baselineQuantity;
        $lastBatch = $batches->first();
        $supplierSignal = $this->supplierSignals->summarize(
            $batches,
            $lastBatch['supplier_id'] ?? null,
        );
        $referenceSupplier = $supplierSignal['reference'];
        $unitCost = (float) ($lastBatch['unit_cost'] ?? 0);

        if ($unitCost <= 0) {
            $unitCost = (float) $product->cost_price;
        }

        $stock = (float) $product->stock_quantity;
        $minimum = (float) $product->minimum_stock;
        $priority = $this->priority($stock, $minimum);

        return [
            'product' => $product,
            'stock_quantity' => $stock,
            'minimum_stock' => $minimum,
            'stock_ratio' => $minimum > 0 ? round($stock / $minimum, 4) : 1,
            'target_stock' => round($stock + $suggestedQuantity, 3),
            'baseline_quantity' => $baselineQuantity,
            'suggested_quantity' => round($suggestedQuantity, 3),
            'unit' => $product->unit ?: 'un',
            'unit_cost' => $unitCost,
            'estimated_cost' => round($suggestedQuantity * $unitCost, 2),
            'priority' => $priority['key'],
            'priority_label' => $priority['label'],
            'priority_rank' => $priority['rank'],
            'confidence' => $this->confidence($historyCount),
            'history_count' => $historyCount,
            'history_window_days' => self::HISTORY_WINDOW_DAYS,
            'average_purchase_quantity' => $averagePurchaseQuantity,
            'average_purchase_interval_days' => $this->averageIntervalDays($batches),
            'uses_purchase_history' => $usesPurchaseHistory,
            'last_purchase_at' => $lastBatch['purchased_at'] ?? null,
            'last_purchase_quantity' => $lastBatch['quantity'] ?? null,
            'last_supplier_id' => $lastBatch['supplier_id'] ?? null,
            'last_supplier_name' => $lastBatch['supplier_name'] ?? null,
            'supplier_profiles' => $supplierSignal['profiles'],
            'reference_supplier_deliveries' => $referenceSupplier['deliveries_count'],
            'reference_supplier_quantity_received' => $referenceSupplier['quantity_received'],
            'reference_supplier_average_batch_quantity' => $referenceSupplier['average_batch_quantity'],
            'reference_supplier_average_unit_cost' => $referenceSupplier['average_unit_cost'],
            'reference_supplier_latest_unit_cost' => $referenceSupplier['latest_unit_cost'],
            'reference_supplier_last_received_at' => $referenceSupplier['last_received_at'],
            'reference_supplier_lead_time_samples' => $referenceSupplier['lead_time_samples'],
            'reference_supplier_average_lead_time_days' => $referenceSupplier['average_lead_time_days'],
            'reference_supplier_minimum_lead_time_days' => $referenceSupplier['minimum_lead_time_days'],
            'reference_supplier_maximum_lead_time_days' => $referenceSupplier['maximum_lead_time_days'],
            'has_reference_supplier_history' => $referenceSupplier['deliveries_count'] > 0,
            'has_supplier_lead_time' => $referenceSupplier['has_lead_time'],
            'demand_window_days' => $demand['window_days'],
            'demand_sales_count' => $demand['sales_count'],
            'demand_sold_quantity' => $demand['sold_quantity'],
            'demand_returned_quantity' => $demand['returned_quantity'],
            'net_demand_quantity' => $demand['net_quantity'],
            'average_monthly_demand' => $demand['average_monthly_quantity'],
            'last_sale_at' => $demand['last_sale_at'],
            'has_recent_demand' => $demand['has_recent_demand'],
            'reason' => $this->reason($stock, $usesPurchaseHistory, $historyCount)
                .' '.$this->demandReason($demand)
                .' '.$this->supplierReason($referenceSupplier),
            'purchase_url' => route('purchase-entries.create', array_filter([
                'replenishment_product' => $product->id,
                'clinic_id' => $product->clinic_id,
                'supplier_id' => $lastBatch['supplier_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''), false),
        ];
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, Collection<int, PurchaseEntryItem>>
     */
    private function historyForProducts(Collection $productIds): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        $cutoff = now()->subDays(self::HISTORY_WINDOW_DAYS)->startOfDay();

        return PurchaseEntryItem::query()
            ->with('purchaseEntry.supplier')
            ->whereIn('product_id', $productIds)
            ->whereHas('purchaseEntry', function ($query) use ($cutoff): void {
                $query
                    ->where('status', 'received')
                    ->where('received_at', '>=', $cutoff);
            })
            ->get()
            ->groupBy('product_id');
    }

    /**
     * @param  Collection<int, PurchaseEntryItem>  $history
     * @return Collection<int, array<string, mixed>>
     */
    private function purchaseBatches(Collection $history): Collection
    {
        return $history
            ->groupBy('purchase_entry_id')
            ->map(function (Collection $items): array {
                /** @var PurchaseEntryItem $firstItem */
                $firstItem = $items->first();
                $quantity = (float) $items->sum(fn (PurchaseEntryItem $item) => (float) $item->quantity);
                $totalCost = (float) $items->sum(
                    fn (PurchaseEntryItem $item) => (float) $item->quantity * (float) $item->unit_cost
                );
                $entry = $firstItem->purchaseEntry;

                return [
                    'quantity' => round($quantity, 3),
                    'unit_cost' => $quantity > 0 ? round($totalCost / $quantity, 2) : 0,
                    'purchased_at' => $entry?->received_at ?? $entry?->purchased_at,
                    'ordered_at' => $entry?->purchased_at,
                    'received_at' => $entry?->received_at,
                    'lead_time_days' => $this->leadTimeDays($entry?->purchased_at, $entry?->received_at),
                    'supplier_id' => $entry?->supplier_id,
                    'supplier_name' => $entry?->supplier?->name,
                ];
            })
            ->sortByDesc(fn (array $batch) => $batch['purchased_at']?->getTimestamp() ?? 0)
            ->values();
    }

    private function averageIntervalDays(Collection $batches): ?int
    {
        $dates = $batches
            ->pluck('purchased_at')
            ->filter()
            ->sortBy(fn ($date) => $date->getTimestamp())
            ->values();

        if ($dates->count() < 2) {
            return null;
        }

        $intervals = collect();

        for ($index = 1; $index < $dates->count(); $index++) {
            $intervals->push($dates[$index - 1]->diffInDays($dates[$index]));
        }

        return (int) round((float) $intervals->avg());
    }

    /**
     * @return array{key: string, label: string, rank: int}
     */
    private function priority(float $stock, float $minimum): array
    {
        if ($stock <= 0) {
            return ['key' => 'critical', 'label' => 'Crítica', 'rank' => 0];
        }

        if ($stock < ($minimum / 2)) {
            return ['key' => 'high', 'label' => 'Alta', 'rank' => 1];
        }

        return ['key' => 'attention', 'label' => 'Atenção', 'rank' => 2];
    }

    private function confidence(int $historyCount): string
    {
        return match (true) {
            $historyCount >= 3 => 'high',
            $historyCount === 2 => 'medium',
            default => 'low',
        };
    }

    private function reason(float $stock, bool $usesPurchaseHistory, int $historyCount): string
    {
        $stockReason = $stock <= 0
            ? 'O produto está sem saldo.'
            : 'O saldo atingiu o estoque mínimo.';

        if ($usesPurchaseHistory) {
            return "{$stockReason} A sugestão cobre o alvo de duas vezes o mínimo e respeita o lote médio de {$historyCount} compras recebidas recentemente.";
        }

        if ($historyCount > 0) {
            return "{$stockReason} O histórico ainda é curto; a sugestão usa o alvo seguro de duas vezes o estoque mínimo.";
        }

        return "{$stockReason} Sem compras recebidas nos últimos ".self::HISTORY_WINDOW_DAYS.' dias; a sugestão usa o alvo seguro de duas vezes o estoque mínimo.';
    }

    /** @param array<string, mixed> $demand */
    private function demandReason(array $demand): string
    {
        if (! $demand['has_recent_demand']) {
            return 'Nenhuma demanda líquida foi registrada nas vendas concluídas dos últimos '.ProductDemandSignalService::WINDOW_DAYS.' dias.';
        }

        $quantity = number_format((float) $demand['net_quantity'], 3, ',', '.');
        $sales = (int) $demand['sales_count'];

        return "A demanda líquida recente foi de {$quantity} unidade(s) em {$sales} venda(s); este sinal ainda não altera a quantidade automaticamente.";
    }

    private function leadTimeDays($purchasedAt, $receivedAt): ?int
    {
        if ($purchasedAt === null || $receivedAt === null || $receivedAt->lt($purchasedAt)) {
            return null;
        }

        return (int) round($purchasedAt->diffInDays($receivedAt));
    }

    /** @param array<string, mixed> $supplier */
    private function supplierReason(array $supplier): string
    {
        if ($supplier['deliveries_count'] === 0) {
            return 'Ainda não há fornecedor identificado em recebimentos recentes deste produto.';
        }

        if (! $supplier['has_lead_time']) {
            return "O fornecedor {$supplier['supplier_name']} possui recebimentos recentes, mas sem datas suficientes para calcular o prazo observado.";
        }

        return "O prazo médio observado de {$supplier['supplier_name']} foi de {$supplier['average_lead_time_days']} dia(s) em {$supplier['lead_time_samples']} recebimento(s); esse histórico não representa promessa de entrega.";
    }
}
