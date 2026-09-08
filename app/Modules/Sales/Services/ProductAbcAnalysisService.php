<?php

namespace App\Modules\Sales\Services;

use App\Modules\Products\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductAbcAnalysisService
{
    public const PERIODS = [
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        '180' => 'Últimos 180 dias',
    ];

    public const CLASSES = [
        'A' => [
            'label' => 'Classe A',
            'description' => 'Produtos que formam a primeira faixa, até 80% da receita líquida acumulada.',
            'tone' => 'success',
        ],
        'B' => [
            'label' => 'Classe B',
            'description' => 'Produtos da faixa seguinte, entre 80% e 95% da receita líquida acumulada.',
            'tone' => 'warning',
        ],
        'C' => [
            'label' => 'Classe C',
            'description' => 'Produtos da faixa final, acima de 95% da receita líquida acumulada.',
            'tone' => 'muted-badge',
        ],
    ];

    public const PER_PAGE = 50;

    public function __construct(private readonly SaleProfitabilityService $profitability) {}

    /** @param array<string, mixed> $filters */
    public function data(array $filters = []): array
    {
        $period = array_key_exists((string) ($filters['period'] ?? ''), self::PERIODS)
            ? (string) $filters['period']
            : '90';
        $to = today();
        $from = today()->subDays(((int) $period) - 1);
        $profitability = $this->profitability->summary(
            $from->toDateString(),
            $to->toDateString(),
            'product'
        );
        $soldItems = $profitability['items']
            ->filter(fn (array $item): bool => $item['product_id'] !== null)
            ->sortByDesc('net_revenue')
            ->values();
        $products = Product::query()
            ->whereIn('id', $soldItems->pluck('product_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $items = $this->classify($soldItems, $products);
        $filteredItems = $this->applyFilters($items, $filters);

        return [
            'stats' => $this->summary($items, (int) $profitability['stats']['sales_count']),
            'items' => $this->paginate($filteredItems, (int) ($filters['page'] ?? 1)),
            'period' => [
                'value' => $period,
                'label' => self::PERIODS[$period],
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'periods' => self::PERIODS,
            'classes' => self::CLASSES,
            'filters' => $filters,
            'filterOptions' => [
                'categories' => $items->pluck('category')->filter()->unique()->sort()->values(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, Product>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function classify(Collection $items, Collection $products): Collection
    {
        $totalRevenue = max(0, (float) $items->sum('net_revenue'));
        $accumulatedRevenue = 0.0;

        return $items->values()->map(function (array $item, int $index) use ($products, $totalRevenue, &$accumulatedRevenue): array {
            $revenue = max(0, (float) $item['net_revenue']);
            $previousPercent = $totalRevenue > 0
                ? ($accumulatedRevenue / $totalRevenue) * 100
                : 100.0;
            $class = $this->classFor($revenue, $previousPercent);
            $accumulatedRevenue += $revenue;
            $product = $products->get($item['product_id']);
            $stockQuantity = max(0, (float) ($product?->stock_quantity ?? 0));
            $stockValue = $stockQuantity * max(0, (float) ($product?->cost_price ?? 0));

            return array_merge($item, [
                'rank' => $index + 1,
                'abc_class' => $class,
                'class_label' => self::CLASSES[$class]['label'],
                'class_tone' => self::CLASSES[$class]['tone'],
                'participation_percent' => $totalRevenue > 0
                    ? round(($revenue / $totalRevenue) * 100, 2)
                    : 0.0,
                'cumulative_percent' => $totalRevenue > 0
                    ? round(($accumulatedRevenue / $totalRevenue) * 100, 2)
                    : 0.0,
                'net_quantity' => round(max(0, (float) $item['quantity'] - (float) $item['returned_quantity']), 3),
                'product' => $product,
                'stock_quantity' => round($stockQuantity, 3),
                'stock_value' => round($stockValue, 2),
            ]);
        });
    }

    private function classFor(float $revenue, float $previousPercent): string
    {
        if ($revenue <= 0) {
            return 'C';
        }

        if ($previousPercent < 80) {
            return 'A';
        }

        if ($previousPercent < 95) {
            return 'B';
        }

        return 'C';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $items, array $filters): Collection
    {
        $query = Str::lower(trim((string) ($filters['q'] ?? '')));
        $class = strtoupper(trim((string) ($filters['class'] ?? '')));
        $category = trim((string) ($filters['category'] ?? ''));

        return $items
            ->filter(function (array $item) use ($query, $class, $category): bool {
                if ($class !== '' && $item['abc_class'] !== $class) {
                    return false;
                }

                if ($category !== '' && $item['category'] !== $category) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                /** @var Product|null $product */
                $product = $item['product'];
                $haystack = Str::lower(implode(' ', array_filter([
                    $item['description'],
                    $item['category'],
                    $product?->sku,
                    $product?->gtin,
                    $product?->barcode,
                    $product?->brand,
                ])));

                return Str::contains($haystack, $query);
            })
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function summary(Collection $items, int $salesCount): array
    {
        $totalRevenue = (float) $items->sum('net_revenue');
        $classes = collect(self::CLASSES)->mapWithKeys(function (array $definition, string $class) use ($items, $totalRevenue): array {
            $classItems = $items->where('abc_class', $class);
            $netRevenue = (float) $classItems->sum('net_revenue');

            return [$class => array_merge($definition, [
                'count' => $classItems->count(),
                'net_revenue' => round($netRevenue, 2),
                'revenue_share_percent' => $totalRevenue > 0
                    ? round(($netRevenue / $totalRevenue) * 100, 2)
                    : 0.0,
                'stock_value' => round((float) $classItems->sum('stock_value'), 2),
            ])];
        })->all();

        return [
            'total_products' => $items->count(),
            'sales_count' => $salesCount,
            'net_quantity' => round((float) $items->sum('net_quantity'), 3),
            'net_revenue' => round((float) $items->sum('net_revenue'), 2),
            'returns' => round((float) $items->sum('returns'), 2),
            'stock_value' => round((float) $items->sum('stock_value'), 2),
            'classes' => $classes,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function paginate(Collection $items, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => route('sales.product-abc'),
                'pageName' => 'page',
            ]
        );
    }
}
