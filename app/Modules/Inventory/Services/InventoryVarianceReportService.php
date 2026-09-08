<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryCountItem;
use App\Modules\Products\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryVarianceReportService
{
    public const PERIODS = [
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        '180' => 'Últimos 180 dias',
        'all' => 'Todo o histórico',
    ];

    public const DIRECTIONS = [
        'divergent' => 'Somente divergências',
        'surplus' => 'Somente sobras',
        'shortage' => 'Somente faltas',
        'match' => 'Sem divergência',
        'all' => 'Todos os produtos contados',
    ];

    public function data(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $baseQuery = $this->baseQuery($filters);
        $rankings = $this->rankingsQuery(
            $this->applyDirection(clone $baseQuery, $filters['direction'])
        )->paginate(20)->withQueryString();

        $this->hydrateProducts($rankings);

        return [
            'rankings' => $rankings,
            'stats' => $this->stats(clone $baseQuery),
            'filters' => $filters,
            'periods' => self::PERIODS,
            'directions' => self::DIRECTIONS,
            'categories' => Product::query()
                ->withTrashed()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
        ];
    }

    public function export(array $filters): StreamedResponse
    {
        $filters = $this->normalizeFilters($filters);
        $rows = $this->rankingsQuery(
            $this->applyDirection($this->baseQuery($filters), $filters['direction'])
        )->get();
        $products = $this->productsFor($rows);
        $filename = 'vetflow-divergencias-inventario-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $products): void {
            $output = fopen('php://output', 'wb');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Produto',
                'Clínica',
                'Categoria',
                'SKU',
                'Unidade',
                'Contagens',
                'Divergências',
                'Variação líquida',
                'Variação absoluta',
                'Valor de sobras',
                'Valor de faltas',
                'Impacto líquido',
                'Última contagem',
            ], ';');

            foreach ($rows as $row) {
                $product = $products->get($row->product_id);

                fputcsv($output, [
                    $this->safeCsvText($product?->name ?? 'Produto removido'),
                    $this->safeCsvText($product?->clinic?->trade_name),
                    $this->safeCsvText($product?->category),
                    $this->safeCsvText($product?->sku),
                    $this->safeCsvText($product?->unit ?? 'un'),
                    (int) $row->count_events,
                    (int) $row->divergence_events,
                    $this->decimal($row->net_quantity, 3),
                    $this->decimal($row->absolute_quantity, 3),
                    $this->decimal($row->surplus_value, 2),
                    $this->decimal($row->shortage_value, 2),
                    $this->decimal($row->net_value, 2),
                    $row->last_counted_at,
                ], ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function baseQuery(array $filters): Builder
    {
        return InventoryCountItem::query()
            ->whereNotNull('variance_quantity')
            ->whereHas('inventoryCount', function ($query) use ($filters): void {
                $query->where('status', 'finalized');

                if ($filters['period'] !== 'all') {
                    $query->where('finalized_at', '>=', now()->subDays((int) $filters['period']));
                }
            })
            ->whereHas('product', function ($query) use ($filters): void {
                $query->withTrashed();

                if ($filters['category']) {
                    $query->where('category', $filters['category']);
                }

                if ($filters['q']) {
                    $search = $filters['q'];
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('gtin', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
                }
            });
    }

    private function applyDirection(Builder $query, string $direction): Builder
    {
        return match ($direction) {
            'surplus' => $query->whereRaw('variance_quantity >= 0.0005'),
            'shortage' => $query->whereRaw('variance_quantity <= -0.0005'),
            'match' => $query->whereRaw('ABS(variance_quantity) < 0.0005'),
            'all' => $query,
            default => $query->whereRaw('ABS(variance_quantity) >= 0.0005'),
        };
    }

    private function rankingsQuery(Builder $query): Builder
    {
        return $query
            ->selectRaw(
                'product_id,
                COUNT(DISTINCT inventory_count_id) as count_events,
                SUM(CASE WHEN ABS(variance_quantity) >= 0.0005 THEN 1 ELSE 0 END) as divergence_events,
                SUM(variance_quantity) as net_quantity,
                SUM(ABS(variance_quantity)) as absolute_quantity,
                SUM(CASE WHEN variance_quantity > 0 THEN variance_quantity * unit_cost_snapshot ELSE 0 END) as surplus_value,
                SUM(CASE WHEN variance_quantity < 0 THEN ABS(variance_quantity) * unit_cost_snapshot ELSE 0 END) as shortage_value,
                SUM(variance_quantity * unit_cost_snapshot) as net_value,
                MAX(updated_at) as last_counted_at'
            )
            ->groupBy('product_id')
            ->orderByRaw('SUM(ABS(variance_quantity) * unit_cost_snapshot) DESC')
            ->orderByDesc('divergence_events')
            ->orderBy('product_id');
    }

    private function stats(Builder $query): array
    {
        $stats = $query->selectRaw(
            'COUNT(*) as total_items,
            COUNT(DISTINCT inventory_count_id) as total_counts,
            COUNT(DISTINCT CASE WHEN ABS(variance_quantity) >= 0.0005 THEN product_id END) as affected_products,
            SUM(CASE WHEN ABS(variance_quantity) >= 0.0005 THEN 1 ELSE 0 END) as divergent_items,
            SUM(CASE WHEN variance_quantity > 0 THEN variance_quantity * unit_cost_snapshot ELSE 0 END) as surplus_value,
            SUM(CASE WHEN variance_quantity < 0 THEN ABS(variance_quantity) * unit_cost_snapshot ELSE 0 END) as shortage_value,
            SUM(ABS(variance_quantity) * unit_cost_snapshot) as absolute_adjustment_value,
            SUM(variance_quantity * unit_cost_snapshot) as net_adjustment_value'
        )->first();

        $totalItems = (int) ($stats?->total_items ?? 0);
        $divergentItems = (int) ($stats?->divergent_items ?? 0);

        return [
            'total_items' => $totalItems,
            'total_counts' => (int) ($stats?->total_counts ?? 0),
            'affected_products' => (int) ($stats?->affected_products ?? 0),
            'divergent_items' => $divergentItems,
            'accuracy_percent' => $totalItems > 0
                ? round((($totalItems - $divergentItems) / $totalItems) * 100, 2)
                : null,
            'surplus_value' => round((float) ($stats?->surplus_value ?? 0), 2),
            'shortage_value' => round((float) ($stats?->shortage_value ?? 0), 2),
            'absolute_adjustment_value' => round((float) ($stats?->absolute_adjustment_value ?? 0), 2),
            'net_adjustment_value' => round((float) ($stats?->net_adjustment_value ?? 0), 2),
        ];
    }

    private function hydrateProducts(LengthAwarePaginator $rankings): void
    {
        $products = $this->productsFor($rankings->getCollection());

        $rankings->setCollection($rankings->getCollection()->map(function ($row) use ($products) {
            $row->product = $products->get($row->product_id);

            return $row;
        }));
    }

    private function productsFor(Collection $rows): Collection
    {
        return Product::query()
            ->withTrashed()
            ->with('clinic')
            ->whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->keyBy('id');
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'period' => (string) ($filters['period'] ?? '90'),
            'direction' => (string) ($filters['direction'] ?? 'divergent'),
            'q' => trim((string) ($filters['q'] ?? '')),
            'category' => trim((string) ($filters['category'] ?? '')),
        ];
    }

    private function decimal(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, ',', '');
    }

    private function safeCsvText(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
