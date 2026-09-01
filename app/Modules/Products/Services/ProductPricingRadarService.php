<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductPricingRadarService
{
    public const LOW_MARGIN_PERCENT = 20.0;

    public const PER_PAGE = 50;

    public const SIGNALS = [
        'missing_cost' => [
            'label' => 'Sem custo',
            'description' => 'Custo unitário ausente ou zerado; margem não confiável.',
            'tone' => 'danger',
        ],
        'missing_price' => [
            'label' => 'Sem preço',
            'description' => 'Preço de venda ausente ou zerado.',
            'tone' => 'danger',
        ],
        'below_cost' => [
            'label' => 'Abaixo do custo',
            'description' => 'Preço de venda menor que o custo unitário atual.',
            'tone' => 'danger',
        ],
        'break_even' => [
            'label' => 'Sem margem',
            'description' => 'Preço de venda igual ao custo unitário atual.',
            'tone' => 'warning',
        ],
        'low_margin' => [
            'label' => 'Margem baixa',
            'description' => 'Margem bruta cadastral positiva, mas abaixo de 20%.',
            'tone' => 'warning',
        ],
        'adequate' => [
            'label' => 'Margem adequada',
            'description' => 'Margem bruta cadastral igual ou superior a 20%.',
            'tone' => 'success',
        ],
    ];

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
                'unit',
                'cost_price',
                'sale_price',
                'stock_quantity',
            ]);
        $items = $products->map(fn (Product $product): array => $this->item($product));
        $filteredItems = $this->applyFilters($items, $filters);

        return [
            'stats' => $this->summary($items),
            'items' => $this->paginate($filteredItems, (int) ($filters['page'] ?? 1)),
            'signals' => self::SIGNALS,
            'filters' => $filters,
            'filterOptions' => [
                'categories' => $products->pluck('category')->filter()->unique()->sort()->values(),
                'brands' => $products->pluck('brand')->filter()->unique()->sort()->values(),
            ],
            'lowMarginPercent' => self::LOW_MARGIN_PERCENT,
        ];
    }

    private function item(Product $product): array
    {
        $cost = max(0, (float) $product->cost_price);
        $price = max(0, (float) $product->sale_price);
        $stock = max(0, (float) $product->stock_quantity);
        $marginAmount = $price - $cost;
        $marginPercent = $price > 0 && $cost > 0
            ? round(($marginAmount / $price) * 100, 2)
            : null;
        $markupPercent = $cost > 0 && $price > 0
            ? round(($marginAmount / $cost) * 100, 2)
            : null;
        $signal = $this->signalFor($cost, $price, $marginPercent);
        $hasKnownMargin = $cost > 0 && $price > 0;

        return [
            'product' => $product,
            'signal' => $signal,
            'signal_label' => self::SIGNALS[$signal]['label'],
            'signal_description' => self::SIGNALS[$signal]['description'],
            'signal_tone' => self::SIGNALS[$signal]['tone'],
            'margin_amount' => $hasKnownMargin ? round($marginAmount, 2) : null,
            'margin_percent' => $marginPercent,
            'markup_percent' => $markupPercent,
            'stock_value' => round($stock * $cost, 2),
            'projected_revenue' => round($stock * $price, 2),
            'projected_gross_profit' => $hasKnownMargin
                ? round($stock * $marginAmount, 2)
                : null,
        ];
    }

    private function signalFor(float $cost, float $price, ?float $marginPercent): string
    {
        if ($cost <= 0) {
            return 'missing_cost';
        }

        if ($price <= 0) {
            return 'missing_price';
        }

        if ($price < $cost) {
            return 'below_cost';
        }

        if ($price === $cost) {
            return 'break_even';
        }

        if ($marginPercent !== null && $marginPercent < self::LOW_MARGIN_PERCENT) {
            return 'low_margin';
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
        $signal = trim((string) ($filters['signal'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $brand = trim((string) ($filters['brand'] ?? ''));

        return $items
            ->filter(function (array $item) use ($query, $signal, $category, $brand): bool {
                /** @var Product $product */
                $product = $item['product'];

                if ($signal !== '' && $item['signal'] !== $signal) {
                    return false;
                }

                if ($category !== '' && $product->category !== $category) {
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
                    $product->category,
                    $product->brand,
                ])));

                return Str::contains($haystack, $query);
            })
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function summary(Collection $items): array
    {
        $signals = collect(self::SIGNALS)->mapWithKeys(function (array $definition, string $signal) use ($items): array {
            $signalItems = $items->where('signal', $signal);

            return [$signal => array_merge($definition, [
                'count' => $signalItems->count(),
                'stock_value' => round((float) $signalItems->sum('stock_value'), 2),
                'projected_revenue' => round((float) $signalItems->sum('projected_revenue'), 2),
            ])];
        })->all();

        return [
            'total' => $items->count(),
            'stock_value' => round((float) $items->sum('stock_value'), 2),
            'projected_revenue' => round((float) $items->sum('projected_revenue'), 2),
            'projected_gross_profit' => round((float) $items->sum('projected_gross_profit'), 2),
            'known_margin_products' => $items->whereNotNull('margin_percent')->count(),
            'signals' => $signals,
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
                'path' => route('products.pricing-radar'),
                'pageName' => 'page',
            ]
        );
    }
}
