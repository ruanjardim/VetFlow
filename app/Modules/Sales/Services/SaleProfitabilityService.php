<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SaleProfitabilityService
{
    public const TYPE_LABELS = [
        'product' => 'Produtos',
        'service' => 'Servicos',
        'custom' => 'Itens avulsos',
    ];

    public function summary(?string $from = null, ?string $to = null, string $type = 'all'): array
    {
        [$start, $end] = $this->range($from, $to);
        $type = array_key_exists($type, self::TYPE_LABELS) ? $type : 'all';

        $sales = Sale::query()
            ->with(['clinic', 'items'])
            ->whereIn('status', ['completed', 'returned'])
            ->whereBetween('sold_at', [$start, $end])
            ->orderBy('sold_at')
            ->get();

        $rows = $sales
            ->flatMap(fn (Sale $sale) => $this->saleRows($sale))
            ->when($type !== 'all', fn (Collection $items) => $items->where('type', $type))
            ->values();

        $items = $this->groupItems($rows);
        $stats = $this->aggregate($rows);
        $stats['negative_margin_items'] = $items
            ->filter(fn (array $item) => (float) $item['gross_profit'] < 0)
            ->count();
        $stats['missing_cost_lines'] = $rows
            ->filter(fn (array $row) => $row['missing_cost'])
            ->count();

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            ],
            'filter' => [
                'type' => $type,
                'type_label' => $type === 'all' ? 'Todos' : self::TYPE_LABELS[$type],
            ],
            'stats' => $stats,
            'by_type' => $this->groupTypes($rows),
            'by_category' => $this->groupCategories($rows),
            'items' => $items,
        ];
    }

    private function saleRows(Sale $sale): Collection
    {
        $items = $sale->items;
        $itemsNetTotal = $items->sum(fn (SaleItem $item) => $this->itemNetTotal($item));
        $saleAdjustment = (float) $sale->total - $itemsNetTotal;

        return $items->map(function (SaleItem $item) use ($sale, $itemsNetTotal, $saleAdjustment) {
            $quantity = (float) $item->quantity;
            $returnedQuantity = min($quantity, max(0, (float) $item->returned_quantity));
            $remainingQuantity = max(0, $quantity - $returnedQuantity);
            $itemNetTotal = $this->itemNetTotal($item);
            $allocatedAdjustment = $itemsNetTotal > 0
                ? $saleAdjustment * ($itemNetTotal / $itemsNetTotal)
                : 0.0;
            $grossRevenue = max(0, $itemNetTotal + $allocatedAdjustment);
            $returns = min($grossRevenue, max(0, (float) $item->refunded_total));
            $netRevenue = max(0, $grossRevenue - $returns);
            $cost = max(0, (float) $item->cost_unit_price * $remainingQuantity);
            $grossProfit = $netRevenue - $cost;

            return [
                'sale_id' => $sale->id,
                'clinic_id' => $sale->clinic_id,
                'clinic_name' => $sale->clinic?->trade_name ?: $sale->clinic?->corporate_name,
                'product_id' => $item->product_id,
                'type' => $item->type,
                'type_label' => self::TYPE_LABELS[$item->type] ?? ucfirst($item->type),
                'catalog_key' => $this->catalogKey($item),
                'description' => $item->product_name_snapshot ?: $item->description,
                'category' => $this->category($item),
                'quantity' => $quantity,
                'returned_quantity' => $returnedQuantity,
                'remaining_quantity' => $remainingQuantity,
                'gross_revenue' => $grossRevenue,
                'returns' => $returns,
                'net_revenue' => $netRevenue,
                'cost' => $cost,
                'gross_profit' => $grossProfit,
                'missing_cost' => $item->type === 'product'
                    && $remainingQuantity > 0
                    && (float) $item->cost_unit_price <= 0,
            ];
        });
    }

    private function groupItems(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['clinic_id'].'|'.$row['type'].'|'.$row['catalog_key'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return array_merge($this->aggregate($group), [
                    'clinic_id' => $first['clinic_id'],
                    'clinic_name' => $first['clinic_name'],
                    'product_id' => $first['product_id'],
                    'catalog_key' => $first['catalog_key'],
                    'type' => $first['type'],
                    'type_label' => $first['type_label'],
                    'description' => $first['description'],
                    'category' => $first['category'],
                    'missing_cost' => $group->contains(fn (array $row) => $row['missing_cost']),
                ]);
            })
            ->sortByDesc(fn (array $item) => [$item['gross_profit'], $item['net_revenue']])
            ->values();
    }

    private function groupTypes(Collection $rows): Collection
    {
        return collect(self::TYPE_LABELS)
            ->map(function (string $label, string $type) use ($rows) {
                return array_merge($this->aggregate($rows->where('type', $type)), [
                    'type' => $type,
                    'label' => $label,
                ]);
            })
            ->values();
    }

    private function groupCategories(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['clinic_id'].'|'.$row['type'].'|'.$row['category'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return array_merge($this->aggregate($group), [
                    'clinic_id' => $first['clinic_id'],
                    'clinic_name' => $first['clinic_name'],
                    'type' => $first['type'],
                    'type_label' => $first['type_label'],
                    'category' => $first['category'],
                ]);
            })
            ->sortByDesc('net_revenue')
            ->values();
    }

    private function aggregate(Collection $rows): array
    {
        $netRevenue = (float) $rows->sum('net_revenue');
        $grossProfit = (float) $rows->sum('gross_profit');
        $salesCount = $rows->pluck('sale_id')->unique()->count();

        return [
            'sales_count' => $salesCount,
            'lines_count' => $rows->count(),
            'quantity' => round((float) $rows->sum('quantity'), 3),
            'returned_quantity' => round((float) $rows->sum('returned_quantity'), 3),
            'gross_revenue' => round((float) $rows->sum('gross_revenue'), 2),
            'returns' => round((float) $rows->sum('returns'), 2),
            'net_revenue' => round($netRevenue, 2),
            'cost' => round((float) $rows->sum('cost'), 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percent' => $netRevenue > 0
                ? round(($grossProfit / $netRevenue) * 100, 2)
                : null,
            'average_ticket' => $salesCount > 0 ? round($netRevenue / $salesCount, 2) : 0.0,
        ];
    }

    private function itemNetTotal(SaleItem $item): float
    {
        return (float) $item->net_total > 0
            ? (float) $item->net_total
            : (float) $item->total;
    }

    private function catalogKey(SaleItem $item): string
    {
        if ($item->product_id) {
            return 'product:'.$item->product_id;
        }

        if ($item->petshop_service_id) {
            return 'service:'.$item->petshop_service_id;
        }

        return 'snapshot:'.mb_strtolower(trim((string) ($item->product_name_snapshot ?: $item->description)));
    }

    private function category(SaleItem $item): string
    {
        if ($item->type === 'service') {
            return $item->category_snapshot ?: 'Servicos';
        }

        if ($item->type === 'custom') {
            return 'Itens avulsos';
        }

        return $item->category_snapshot ?: 'Sem categoria';
    }

    private function range(?string $from, ?string $to): array
    {
        $start = $from
            ? Carbon::parse($from)->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $end = $to
            ? Carbon::parse($to)->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }
}
