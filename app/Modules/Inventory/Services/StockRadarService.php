<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Services\ProductDemandSignalService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StockRadarService
{
    public const NEW_PRODUCT_DAYS = 30;

    public const HIGH_COVERAGE_DAYS = 180;

    public const PER_PAGE = 50;

    public const CATEGORIES = [
        'replenish' => [
            'label' => 'Repor',
            'description' => 'Estoque no mínimo configurado ou abaixo dele.',
            'tone' => 'danger',
        ],
        'new' => [
            'label' => 'Novos',
            'description' => 'Produto cadastrado nos últimos 30 dias.',
            'tone' => 'muted-badge',
        ],
        'stalled' => [
            'label' => 'Sem saída recente',
            'description' => 'Saldo positivo sem demanda líquida nos últimos 90 dias.',
            'tone' => 'warning',
        ],
        'high_coverage' => [
            'label' => 'Cobertura alta',
            'description' => 'Mais de 180 dias de cobertura pelo ritmo líquido observado.',
            'tone' => 'warning',
        ],
        'unparameterized' => [
            'label' => 'Sem mínimo',
            'description' => 'Produto sem estoque mínimo configurado e sem outro sinal prioritário.',
            'tone' => 'muted-badge',
        ],
        'adequate' => [
            'label' => 'Adequado',
            'description' => 'Sem sinal de reposição, parada ou cobertura alta nesta leitura.',
            'tone' => 'success',
        ],
    ];

    public function __construct(private readonly ProductDemandSignalService $demandSignals) {}

    /** @param array<string, mixed> $filters */
    public function data(array $filters = []): array
    {
        $products = Product::query()
            ->active()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'category',
                'brand',
                'sku',
                'gtin',
                'barcode',
                'cost_price',
                'stock_quantity',
                'minimum_stock',
                'unit',
                'created_at',
            ]);
        $signals = $this->demandSignals->signalsForProducts($products->pluck('id'));
        $items = $products
            ->map(fn (Product $product): array => $this->item(
                $product,
                $signals->get($product->id, $this->demandSignals->emptySignal())
            ));
        $filteredItems = $this->applyFilters($items, $filters);

        return [
            'stats' => $this->summary($items),
            'items' => $this->paginate($filteredItems, (int) ($filters['page'] ?? 1)),
            'categories' => self::CATEGORIES,
            'filters' => $filters,
            'filterOptions' => [
                'product_categories' => $products->pluck('category')->filter()->unique()->sort()->values(),
                'brands' => $products->pluck('brand')->filter()->unique()->sort()->values(),
            ],
            'demandWindowDays' => ProductDemandSignalService::WINDOW_DAYS,
            'newProductDays' => self::NEW_PRODUCT_DAYS,
            'highCoverageDays' => self::HIGH_COVERAGE_DAYS,
        ];
    }

    /** @param array<string, mixed> $demandSignal */
    private function item(Product $product, array $demandSignal): array
    {
        $stock = (float) $product->stock_quantity;
        $minimumStock = max(0, (float) $product->minimum_stock);
        $netDemand = max(0, (float) ($demandSignal['net_quantity'] ?? 0));
        $dailyDemand = $netDemand > 0
            ? $netDemand / ProductDemandSignalService::WINDOW_DAYS
            : 0.0;
        $coverageDays = $dailyDemand > 0 && $stock > 0
            ? round($stock / $dailyDemand, 1)
            : null;
        $category = $this->categoryFor($product, $stock, $minimumStock, $netDemand, $coverageDays);

        return [
            'product' => $product,
            'category' => $category,
            'category_label' => self::CATEGORIES[$category]['label'],
            'category_description' => self::CATEGORIES[$category]['description'],
            'category_tone' => self::CATEGORIES[$category]['tone'],
            'stock_value' => round(max(0, $stock) * max(0, (float) $product->cost_price), 2),
            'net_demand' => round($netDemand, 3),
            'coverage_days' => $coverageDays,
            'demand_signal' => $demandSignal,
        ];
    }

    private function categoryFor(
        Product $product,
        float $stock,
        float $minimumStock,
        float $netDemand,
        ?float $coverageDays,
    ): string {
        if ($minimumStock > 0 && $stock <= $minimumStock) {
            return 'replenish';
        }

        if ($product->created_at && $product->created_at->gte(now()->subDays(self::NEW_PRODUCT_DAYS)->startOfDay())) {
            return 'new';
        }

        if ($stock > 0 && $netDemand <= 0) {
            return 'stalled';
        }

        if ($coverageDays !== null && $coverageDays > self::HIGH_COVERAGE_DAYS) {
            return 'high_coverage';
        }

        if ($minimumStock <= 0) {
            return 'unparameterized';
        }

        return 'adequate';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $items, array $filters): Collection
    {
        $query = Str::lower(trim((string) ($filters['q'] ?? '')));
        $category = (string) ($filters['category'] ?? '');
        $productCategory = trim((string) ($filters['product_category'] ?? ''));
        $brand = trim((string) ($filters['brand'] ?? ''));

        return $items
            ->filter(function (array $item) use ($query, $category, $productCategory, $brand): bool {
                /** @var Product $product */
                $product = $item['product'];

                if ($category !== '' && $item['category'] !== $category) {
                    return false;
                }

                if ($productCategory !== '' && $product->category !== $productCategory) {
                    return false;
                }

                if ($brand !== '' && $product->brand !== $brand) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', array_filter([
                    $product->name,
                    $product->sku,
                    $product->gtin,
                    $product->barcode,
                    $product->brand,
                    $product->category,
                ])));

                return Str::contains($haystack, $query);
            })
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function summary(Collection $items): array
    {
        $categories = collect(self::CATEGORIES)->mapWithKeys(function (array $definition, string $key) use ($items): array {
            $categoryItems = $items->where('category', $key);

            return [$key => [
                ...$definition,
                'count' => $categoryItems->count(),
                'stock_value' => round((float) $categoryItems->sum('stock_value'), 2),
            ]];
        })->all();

        return [
            'total' => $items->count(),
            'stock_value' => round((float) $items->sum('stock_value'), 2),
            'categories' => $categories,
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
                'path' => route('inventory-movements.radar'),
                'pageName' => 'page',
            ]
        );
    }
}
