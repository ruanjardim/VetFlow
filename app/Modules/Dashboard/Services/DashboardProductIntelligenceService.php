<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductSource;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\Gtin;
use Illuminate\Support\Collection;

class DashboardProductIntelligenceService
{
    public function summary(): array
    {
        $products = GlobalProduct::query()
            ->withCount(['products', 'sources'])
            ->latest('updated_at')
            ->get();

        $total = $products->count();
        $localProducts = Product::query()->count();
        $linkedLocalProducts = Product::query()->whereNotNull('global_product_id')->count();
        $localWithGtin = Product::query()
            ->where(function ($query) {
                $query
                    ->whereNotNull('gtin')
                    ->orWhereNotNull('barcode');
            })
            ->count();
        $unlinkedLocalWithGtin = Product::query()
            ->whereNull('global_product_id')
            ->where(function ($query) {
                $query
                    ->whereNotNull('gtin')
                    ->orWhereNotNull('barcode');
            })
            ->count();
        $localInvalidGtins = $this->invalidLocalGtins();
        $localWithoutPrice = Product::query()
            ->where(function ($query) {
                $query->whereNull('sale_price')->orWhere('sale_price', '<=', 0);
            })
            ->count();
        $localWithoutImage = Product::query()
            ->where(function ($query) {
                $query->whereNull('image_path')->orWhere('image_path', '');
            })
            ->count();
        $localLowStock = Product::query()
            ->where('minimum_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();

        $qualityScores = $products->map(fn (GlobalProduct $product) => $this->qualityScore($product));
        $lowQuality = $qualityScores->filter(fn (int $score) => $score < 55)->count();
        $averageQuality = $qualityScores->count() > 0 ? round($qualityScores->average(), 1) : 0.0;
        $missingImages = $products
            ->filter(fn (GlobalProduct $product) => ! $product->image_path && ! $product->image_url)
            ->count();
        $staleProducts = $products
            ->filter(fn (GlobalProduct $product) => ! $product->last_lookup_at || $product->last_lookup_at->lt(now()->subDays(30)))
            ->count();
        $withoutLocalUse = $products
            ->filter(fn (GlobalProduct $product) => (int) $product->products_count === 0)
            ->count();

        $stats = [
            'total' => $total,
            'verified' => $products->where('status', GlobalProduct::STATUS_VERIFIED)->count(),
            'pending' => $products->where('status', GlobalProduct::STATUS_PENDING)->count(),
            'conflict' => $products->where('status', GlobalProduct::STATUS_CONFLICT)->count(),
            'missing_image' => $missingImages,
            'stale' => $staleProducts,
            'low_quality' => $lowQuality,
            'average_quality' => $averageQuality,
            'suggestions_pending' => GlobalProductSuggestion::query()
                ->where('status', GlobalProduct::STATUS_PENDING)
                ->count(),
            'local_products' => $localProducts,
            'linked_local_products' => $linkedLocalProducts,
            'local_with_gtin' => $localWithGtin,
            'unlinked_local_with_gtin' => $unlinkedLocalWithGtin,
            'local_invalid_gtin' => $localInvalidGtins,
            'local_without_price' => $localWithoutPrice,
            'local_without_image' => $localWithoutImage,
            'local_low_stock' => $localLowStock,
            'coverage_percent' => $localProducts > 0 ? round(($linkedLocalProducts / $localProducts) * 100, 1) : 0.0,
            'without_local_use' => $withoutLocalUse,
        ];

        return [
            'health' => $this->health($stats),
            'stats' => $stats,
            'actions' => $this->actions($stats),
            'recent' => $this->recentProducts($products),
            'sources' => $this->topSources(),
            'categories' => $this->topCategories($products),
            'routes' => $this->routes(),
        ];
    }

    private function health(array $stats): array
    {
        $critical = $stats['conflict'] + $stats['suggestions_pending'] + $stats['local_invalid_gtin'] + $stats['local_low_stock'];
        $attention = $stats['pending'] + $stats['missing_image'] + $stats['stale'] + $stats['low_quality'] + $stats['unlinked_local_with_gtin'] + $stats['local_without_price'] + $stats['local_without_image'];

        if ($critical > 0) {
            return [
                'label' => 'Acao necessaria',
                'level' => 'danger',
                'description' => 'Ha conflitos, EAN invalido ou estoque local em nivel critico.',
            ];
        }

        if ($attention > 0) {
            return [
                'label' => 'Em evolucao',
                'level' => 'warning',
                'description' => 'O catalogo esta aprendendo, mas ainda possui cadastros para melhorar.',
            ];
        }

        return [
            'label' => 'Saudavel',
            'level' => 'success',
            'description' => 'Catalogo global sem pendencias relevantes no momento.',
        ];
    }

    private function actions(array $stats): array
    {
        return array_values(array_filter([
            $this->action(
                $stats['local_invalid_gtin'],
                'Corrigir EAN local',
                'Produtos com codigo invalido falham no scanner e nas consultas externas.',
                'danger',
                route('products.index', ['intelligence' => 'invalid_gtin'])
            ),
            $this->action(
                $stats['local_low_stock'],
                'Repor estoque local',
                'Produtos abaixo do minimo podem faltar no PDV e nos atendimentos.',
                'danger',
                route('products.index', ['intelligence' => 'low_stock'])
            ),
            $this->action(
                $stats['conflict'],
                'Revisar conflitos globais',
                'Produtos com dados divergentes entre fontes precisam de decisao.',
                'danger',
                route('global-products.index', ['status' => GlobalProduct::STATUS_CONFLICT])
            ),
            $this->action(
                $stats['suggestions_pending'],
                'Revisar sugestoes Intelligence',
                'EANs nao encontrados e sugestoes aguardando avaliacao.',
                'warning',
                route('global-products.suggestions', ['status' => GlobalProduct::STATUS_PENDING])
            ),
            $this->action(
                $stats['missing_image'],
                'Completar imagens globais',
                'Produtos sem imagem dificultam conferencia visual no estoque e PDV.',
                'warning',
                route('global-products.index', ['missing_image' => 1])
            ),
            $this->action(
                $stats['stale'],
                'Reenriquecer consultas antigas',
                'Produtos sem consulta recente podem ter dados melhores nas fontes externas.',
                'warning',
                route('global-products.index', ['stale' => 1])
            ),
            $this->action(
                $stats['unlinked_local_with_gtin'],
                'Vincular produtos locais',
                'Produtos locais com EAN podem aprender e alimentar o Catalogo Global.',
                'info',
                route('products.index', ['intelligence' => 'unlinked'])
            ),
            $this->action(
                $stats['local_without_price'],
                'Completar preco local',
                'Produtos sem preco entram zerados na venda.',
                'warning',
                route('products.index', ['intelligence' => 'without_price'])
            ),
            $this->action(
                $stats['local_without_image'],
                'Completar imagens locais',
                'Imagens ajudam a conferir rapidamente o item no estoque e PDV.',
                'info',
                route('products.index', ['intelligence' => 'without_image'])
            ),
            $this->action(
                $stats['low_quality'],
                'Melhorar qualidade baixa',
                'Cadastros globais incompletos reduzem automacao nas proximas clinicas.',
                'info',
                route('global-products.index', ['quality' => 'low'])
            ),
        ]));
    }

    private function action(int $count, string $title, string $description, string $level, string $url): ?array
    {
        if ($count <= 0) {
            return null;
        }

        return compact('count', 'title', 'description', 'level', 'url');
    }

    private function recentProducts(Collection $products): Collection
    {
        return $products
            ->take(6)
            ->map(fn (GlobalProduct $product) => [
                'id' => $product->id,
                'name' => $product->name ?: 'Produto sem nome',
                'gtin' => $product->gtin,
                'brand' => $product->brand,
                'status' => $product->status,
                'quality' => $this->qualityScore($product),
                'updated_at' => $product->updated_at,
                'url' => route('global-products.show', $product->id),
            ])
            ->values();
    }

    private function topSources(): Collection
    {
        return GlobalProductSource::query()
            ->selectRaw('source_name, COUNT(*) as total, AVG(confidence) as average_confidence')
            ->groupBy('source_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (GlobalProductSource $source) => [
                'name' => $source->source_name ?: 'vetflow',
                'total' => (int) $source->total,
                'average_confidence' => round((float) $source->average_confidence, 1),
            ]);
    }

    private function topCategories(Collection $products): Collection
    {
        return $products
            ->filter(fn (GlobalProduct $product) => filled($product->category))
            ->groupBy('category')
            ->map(fn (Collection $items, string $category) => [
                'category' => $category,
                'total' => $items->count(),
                'url' => route('global-products.index', ['category' => $category]),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();
    }

    private function routes(): array
    {
        return [
            'catalog' => route('global-products.index'),
            'suggestions' => route('global-products.suggestions'),
            'conflicts' => route('global-products.index', ['status' => GlobalProduct::STATUS_CONFLICT]),
            'missing_image' => route('global-products.index', ['missing_image' => 1]),
            'stale' => route('global-products.index', ['stale' => 1]),
            'low_quality' => route('global-products.index', ['quality' => 'low']),
            'products_diagnostics' => route('products.diagnostics'),
            'products_unlinked' => route('products.index', ['intelligence' => 'unlinked']),
            'products_invalid_gtin' => route('products.index', ['intelligence' => 'invalid_gtin']),
            'products_without_price' => route('products.index', ['intelligence' => 'without_price']),
            'products_without_image' => route('products.index', ['intelligence' => 'without_image']),
            'products_low_stock' => route('products.index', ['intelligence' => 'low_stock']),
        ];
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

    private function invalidLocalGtins(): int
    {
        return Product::query()
            ->select(['gtin', 'barcode'])
            ->get()
            ->filter(function (Product $product) {
                $gtin = Gtin::normalize($product->gtin ?: $product->barcode);

                return $gtin !== null && ! Gtin::looksValid($gtin);
            })
            ->count();
    }
}
