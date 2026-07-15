<?php

namespace App\Modules\ProductIntelligence\Services;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GlobalProductCatalogService
{
    public function __construct(
        private readonly ProductIntelligenceService $intelligence,
        private readonly ProductService $productService
    ) {
    }

    public function indexData(Request $request): array
    {
        $query = $this->filteredQuery($request)
            ->withCount(['sources', 'products', 'images', 'regulatoryData'])
            ->latest('updated_at');

        $globalProducts = $query->paginate(15)->withQueryString();
        $globalProducts->getCollection()->transform(fn (GlobalProduct $product) => $this->decorate($product));

        return [
            'globalProducts' => $globalProducts,
            'stats' => $this->stats(),
            'statuses' => $this->statuses(),
            'categories' => $this->categories(),
            'sources' => $this->sources(),
            'qualityBuckets' => $this->qualityBuckets(),
        ];
    }

    public function showData(GlobalProduct $globalProduct): array
    {
        $globalProduct->load([
            'sources' => fn ($query) => $query->latest('queried_at'),
            'images' => fn ($query) => $query->orderByDesc('is_primary')->latest(),
            'regulatoryData' => fn ($query) => $query->latest(),
            'products' => fn ($query) => $query->latest(),
        ]);

        return [
            'product' => $this->decorate($globalProduct),
            'statuses' => $this->statuses(),
            'qualityFlags' => $this->qualityFlags($globalProduct),
            'suggestions' => GlobalProductSuggestion::query()
                ->where('gtin', $globalProduct->gtin)
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }

    public function suggestionsData(Request $request): array
    {
        $query = GlobalProductSuggestion::query()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('suggestion_type', $type);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('gtin', 'like', "%{$search}%")
                    ->orWhere('suggested_name', 'like', "%{$search}%")
                    ->orWhere('source_name', 'like', "%{$search}%");
            });
        }

        return [
            'suggestions' => $query->paginate(20)->withQueryString(),
            'statuses' => $this->statuses(),
            'types' => GlobalProductSuggestion::query()
                ->select('suggestion_type')
                ->distinct()
                ->orderBy('suggestion_type')
                ->pluck('suggestion_type')
                ->filter()
                ->values(),
            'stats' => [
                'total' => GlobalProductSuggestion::query()->count(),
                'pending' => GlobalProductSuggestion::query()->where('status', GlobalProduct::STATUS_PENDING)->count(),
                'verified' => GlobalProductSuggestion::query()->where('status', GlobalProduct::STATUS_VERIFIED)->count(),
                'conflict' => GlobalProductSuggestion::query()->where('status', GlobalProduct::STATUS_CONFLICT)->count(),
            ],
        ];
    }

    public function statuses(): array
    {
        return [
            GlobalProduct::STATUS_PENDING => 'Pendente',
            GlobalProduct::STATUS_VERIFIED => 'Verificado',
            GlobalProduct::STATUS_CONFLICT => 'Conflito',
        ];
    }

    public function updateStatus(GlobalProduct $globalProduct, string $status, ?string $reviewNote = null): void
    {
        $metadata = $globalProduct->metadata ?? [];
        $metadata['last_review'] = [
            'status' => $status,
            'note' => $reviewNote,
            'reviewed_at' => now()->toDateTimeString(),
            'reviewed_by_user_id' => auth()->id(),
        ];

        $globalProduct->update([
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }

    public function enrich(GlobalProduct $globalProduct): ?GlobalProduct
    {
        return $this->intelligence->enrichGlobalProduct($globalProduct);
    }

    public function promoteToLocalProduct(GlobalProduct $globalProduct, array $overrides = []): Product
    {
        return $this->productService->createFromGlobalProduct($globalProduct, $overrides);
    }

    public function syncLocalProducts(GlobalProduct $globalProduct): int
    {
        $globalProduct->loadMissing('products');
        $count = 0;

        foreach ($globalProduct->products as $product) {
            $product->update(array_filter([
                'name' => $globalProduct->name ?: $product->name,
                'brand' => $globalProduct->brand ?: $product->brand,
                'manufacturer' => $globalProduct->manufacturer ?: $product->manufacturer,
                'category' => $globalProduct->category ?: $product->category,
                'description' => $globalProduct->description ?: $product->description,
                'weight' => $globalProduct->weight ?: $product->weight,
                'unit' => $globalProduct->unit ?: $product->unit,
                'image_path' => $globalProduct->image_path ?: $product->image_path,
                'lookup_source' => $globalProduct->api_source ?: $product->lookup_source,
                'lookup_metadata' => array_merge($product->lookup_metadata ?? [], [
                    'synced_from_global_product_id' => $globalProduct->id,
                    'synced_at' => now()->toDateTimeString(),
                ]),
            ], fn ($value) => $value !== null && $value !== ''));

            $count++;
        }

        return $count;
    }

    public function reviewSuggestion(GlobalProductSuggestion $suggestion, string $status): void
    {
        $suggestion->update([
            'status' => $status,
            'reviewed_at' => now(),
        ]);
    }

    public function exportRows(Request $request): Collection
    {
        return $this->filteredQuery($request)
            ->latest('updated_at')
            ->get()
            ->map(function (GlobalProduct $product) {
                $decorated = $this->decorate($product);

                return [
                    $product->gtin,
                    $product->name,
                    $product->brand,
                    $product->manufacturer,
                    $product->category,
                    $product->status,
                    (float) $product->source_confidence,
                    $product->api_source,
                    optional($product->last_lookup_at)->format('Y-m-d H:i:s'),
                    $decorated->quality_score,
                ];
            });
    }

    public function decorate(GlobalProduct $product): GlobalProduct
    {
        $product->quality_score = $this->qualityScore($product);
        $product->quality_status = $this->qualityStatus((int) $product->quality_score);
        $product->quality_flags = $this->qualityFlags($product);

        return $product;
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = GlobalProduct::query();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('gtin', 'like', "%{$search}%")
                    ->orWhere('ean', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($source = $request->query('source')) {
            $query->where('api_source', $source);
        }

        if ($request->boolean('missing_image')) {
            $query->whereNull('image_path')->whereNull('image_url');
        }

        if ($request->boolean('stale')) {
            $query->where(function ($builder) {
                $builder
                    ->whereNull('last_lookup_at')
                    ->orWhere('last_lookup_at', '<', now()->subDays(30));
            });
        }

        if ($quality = $request->query('quality')) {
            $ranges = $this->qualityBuckets();

            if (isset($ranges[$quality])) {
                [$min, $max] = $ranges[$quality]['range'];
                $query->whereBetween('source_confidence', [$min, $max]);
            }
        }

        return $query;
    }

    private function stats(): array
    {
        return [
            'total' => GlobalProduct::query()->count(),
            'pending' => GlobalProduct::query()->where('status', GlobalProduct::STATUS_PENDING)->count(),
            'verified' => GlobalProduct::query()->where('status', GlobalProduct::STATUS_VERIFIED)->count(),
            'conflict' => GlobalProduct::query()->where('status', GlobalProduct::STATUS_CONFLICT)->count(),
            'missing_image' => GlobalProduct::query()->whereNull('image_path')->whereNull('image_url')->count(),
            'stale' => GlobalProduct::query()
                ->where(function ($query) {
                    $query
                        ->whereNull('last_lookup_at')
                        ->orWhere('last_lookup_at', '<', now()->subDays(30));
                })
                ->count(),
            'linked_products' => Product::query()->whereNotNull('global_product_id')->count(),
            'suggestions_pending' => GlobalProductSuggestion::query()->where('status', GlobalProduct::STATUS_PENDING)->count(),
        ];
    }

    private function categories(): Collection
    {
        return GlobalProduct::query()
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();
    }

    private function sources(): Collection
    {
        return GlobalProduct::query()
            ->select('api_source')
            ->whereNotNull('api_source')
            ->distinct()
            ->orderBy('api_source')
            ->pluck('api_source')
            ->filter()
            ->values();
    }

    private function qualityScore(GlobalProduct $product): int
    {
        $score = 0;
        $score += $product->name ? 18 : 0;
        $score += $product->brand ? 10 : 0;
        $score += $product->manufacturer ? 10 : 0;
        $score += $product->category ? 10 : 0;
        $score += $product->description ? 8 : 0;
        $score += $product->weight ? 6 : 0;
        $score += $product->unit ? 6 : 0;
        $score += ($product->image_path || $product->image_url) ? 10 : 0;
        $score += $product->gtin ? 8 : 0;
        $score += min(14, (int) round(((float) $product->source_confidence / 100) * 14));

        return min(100, $score);
    }

    private function qualityFlags(GlobalProduct $product): array
    {
        $flags = [];

        foreach ([
            'name' => 'Nome ausente',
            'brand' => 'Marca ausente',
            'manufacturer' => 'Fabricante ausente',
            'category' => 'Categoria ausente',
            'description' => 'Descricao ausente',
            'weight' => 'Peso/volume ausente',
            'unit' => 'Unidade ausente',
        ] as $field => $label) {
            if (! $product->{$field}) {
                $flags[] = $label;
            }
        }

        if (! $product->image_path && ! $product->image_url) {
            $flags[] = 'Imagem ausente';
        }

        if (! $product->last_lookup_at || $product->last_lookup_at->lt(now()->subDays(30))) {
            $flags[] = 'Consulta antiga';
        }

        if ((float) $product->source_confidence < 60) {
            $flags[] = 'Confianca baixa';
        }

        return $flags;
    }

    private function qualityStatus(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Boa',
            $score >= 55 => 'Media',
            default => 'Baixa',
        };
    }

    private function qualityBuckets(): array
    {
        return [
            'low' => ['label' => 'Baixa', 'range' => [0, 54]],
            'medium' => ['label' => 'Media', 'range' => [55, 79]],
            'high' => ['label' => 'Alta', 'range' => [80, 100]],
        ];
    }
}
