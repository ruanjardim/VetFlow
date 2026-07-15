<?php

namespace App\Modules\Products\Services;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\Gtin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductIntelligenceAuditService
{
    public function indexData(Request $request): array
    {
        $products = $this->filteredQuery($request)
            ->with('globalProduct')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $products->getCollection()->transform(fn (Product $product) => $this->decorate($product));

        $stats = $this->stats();

        return [
            'products' => $products,
            'intelligenceStats' => $stats,
            'intelligenceActions' => $this->actions($stats),
            'intelligenceOptions' => $this->intelligenceOptions(),
            'filterOptions' => $this->filterOptions(),
            'activeFilters' => $this->activeFilters($request),
        ];
    }

    public function diagnosticsData(Request $request): array
    {
        $data = $this->indexData($request);
        $data['products'] = $this->filteredQuery($request)
            ->with('globalProduct')
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();
        $data['products']->getCollection()->transform(fn (Product $product) => $this->decorate($product));
        $data['recentGlobalLinks'] = Product::query()
            ->with('globalProduct')
            ->whereNotNull('global_product_id')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => $this->decorate($product));

        return $data;
    }

    public function stats(): array
    {
        $total = Product::query()->count();
        $linked = Product::query()->whereNotNull('global_product_id')->count();
        $withGtin = Product::query()->where(fn (Builder $query) => $this->whereHasGtin($query))->count();
        $unlinkedWithGtin = Product::query()
            ->whereNull('global_product_id')
            ->where(fn (Builder $query) => $this->whereHasGtin($query))
            ->count();
        $withoutGtin = Product::query()->where(fn (Builder $query) => $this->whereMissingGtin($query))->count();
        $invalidGtin = count($this->invalidProductIds());
        $withoutPrice = Product::query()
            ->where(fn (Builder $query) => $query->whereNull('sale_price')->orWhere('sale_price', '<=', 0))
            ->count();
        $withoutImage = Product::query()
            ->where(fn (Builder $query) => $query->whereNull('image_path')->orWhere('image_path', ''))
            ->count();
        $lowStock = Product::query()
            ->where('minimum_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();
        $globalPending = Product::query()
            ->whereHas('globalProduct', fn (Builder $query) => $query->where('status', GlobalProduct::STATUS_PENDING))
            ->count();
        $globalConflict = Product::query()
            ->whereHas('globalProduct', fn (Builder $query) => $query->where('status', GlobalProduct::STATUS_CONFLICT))
            ->count();

        return [
            'total' => $total,
            'linked' => $linked,
            'with_gtin' => $withGtin,
            'without_gtin' => $withoutGtin,
            'unlinked_with_gtin' => $unlinkedWithGtin,
            'invalid_gtin' => $invalidGtin,
            'without_price' => $withoutPrice,
            'without_image' => $withoutImage,
            'low_stock' => $lowStock,
            'global_pending' => $globalPending,
            'global_conflict' => $globalConflict,
            'coverage_percent' => $total > 0 ? round(($linked / $total) * 100, 1) : 0.0,
        ];
    }

    public function decorate(Product $product): Product
    {
        $flags = $this->flags($product);

        $product->intelligence_gtin = $this->gtin($product);
        $product->intelligence_flags = $flags;
        $product->intelligence_level = $this->level($flags);
        $product->intelligence_label = $this->labelForLevel($product->intelligence_level);
        $product->global_status_label = $this->globalStatusLabel($product->globalProduct?->status);

        return $product;
    }

    public function flags(Product $product): array
    {
        $flags = [];
        $gtin = $this->gtin($product);
        $global = $product->globalProduct;

        if (! $gtin) {
            $flags[] = $this->flag('without_gtin', 'Sem EAN', 'warning', 'Produto sem codigo para busca inteligente.');
        } elseif (! Gtin::looksValid($gtin)) {
            $flags[] = $this->flag('invalid_gtin', 'EAN invalido', 'danger', 'Codigo fora do padrao de leitura.');
        } elseif (! $product->global_product_id) {
            $flags[] = $this->flag('unlinked', 'Sem catalogo global', 'warning', 'Pode ser vinculado ao Catalogo Global VetFlow.');
        }

        if ($global?->status === GlobalProduct::STATUS_CONFLICT) {
            $flags[] = $this->flag('global_conflict', 'Conflito global', 'danger', 'Fontes divergiram sobre este produto.');
        }

        if ($global?->status === GlobalProduct::STATUS_PENDING) {
            $flags[] = $this->flag('global_pending', 'Global pendente', 'warning', 'Produto aprendido aguardando validacao.');
        }

        if ((float) $product->sale_price <= 0) {
            $flags[] = $this->flag('without_price', 'Sem preco', 'warning', 'Defina o preco de venda local.');
        }

        if (! $product->image_path && ! $global?->image_path && ! $global?->image_url) {
            $flags[] = $this->flag('without_image', 'Sem imagem', 'info', 'Imagem ajuda na conferencia no estoque e PDV.');
        }

        if ((float) $product->minimum_stock > 0 && (float) $product->stock_quantity <= (float) $product->minimum_stock) {
            $flags[] = $this->flag('low_stock', 'Estoque baixo', 'danger', 'Reposicao recomendada.');
        }

        if (! $product->category) {
            $flags[] = $this->flag('missing_category', 'Sem categoria', 'info', 'Categoria melhora filtros e indicadores.');
        }

        return $flags;
    }

    public function filteredQuery(Request $request): Builder
    {
        $query = Product::query();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('gtin', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }

        if ($status = $request->query('status')) {
            $query->where('active', $status === 'active');
        }

        match ($request->query('intelligence')) {
            'linked' => $query->whereNotNull('global_product_id'),
            'unlinked' => $query->whereNull('global_product_id')->where(fn (Builder $builder) => $this->whereHasGtin($builder)),
            'without_gtin' => $query->where(fn (Builder $builder) => $this->whereMissingGtin($builder)),
            'invalid_gtin' => $this->whereInvalidGtin($query),
            'without_price' => $query->where(fn (Builder $builder) => $builder->whereNull('sale_price')->orWhere('sale_price', '<=', 0)),
            'without_image' => $query->where(fn (Builder $builder) => $builder->whereNull('image_path')->orWhere('image_path', '')),
            'global_pending' => $query->whereHas('globalProduct', fn (Builder $builder) => $builder->where('status', GlobalProduct::STATUS_PENDING)),
            'global_conflict' => $query->whereHas('globalProduct', fn (Builder $builder) => $builder->where('status', GlobalProduct::STATUS_CONFLICT)),
            'low_stock' => $query->where('minimum_stock', '>', 0)->whereColumn('stock_quantity', '<=', 'minimum_stock'),
            'missing_category' => $query->where(fn (Builder $builder) => $builder->whereNull('category')->orWhere('category', '')),
            default => null,
        };

        return $query;
    }

    private function actions(array $stats): array
    {
        return array_values(array_filter([
            $this->action($stats['global_conflict'], 'Revisar conflitos globais', 'Produtos locais apontando para dados globais conflitantes.', 'danger', 'global_conflict'),
            $this->action($stats['invalid_gtin'], 'Corrigir EAN invalido', 'Codigos fora do padrao podem falhar no scanner e nas APIs.', 'danger', 'invalid_gtin'),
            $this->action($stats['low_stock'], 'Repor estoque baixo', 'Produtos no minimo precisam de compra ou ajuste.', 'danger', 'low_stock'),
            $this->action($stats['unlinked_with_gtin'], 'Vincular ao Catalogo Global', 'Produtos com EAN podem alimentar o VetFlow Intelligence.', 'warning', 'unlinked'),
            $this->action($stats['without_price'], 'Completar precos de venda', 'Produtos sem preco entram zerados no PDV.', 'warning', 'without_price'),
            $this->action($stats['global_pending'], 'Validar aprendizados globais', 'Produtos aprendidos aguardam revisao de qualidade.', 'warning', 'global_pending'),
            $this->action($stats['without_image'], 'Adicionar imagens', 'Imagem facilita conferencia visual no PDV e estoque.', 'info', 'without_image'),
            $this->action($stats['without_gtin'], 'Completar EAN', 'Sem codigo, o produto nao participa da leitura inteligente.', 'info', 'without_gtin'),
        ]));
    }

    private function action(int $count, string $title, string $description, string $level, string $filter): ?array
    {
        if ($count <= 0) {
            return null;
        }

        return [
            'count' => $count,
            'title' => $title,
            'description' => $description,
            'level' => $level,
            'url' => route('products.index', ['intelligence' => $filter]),
            'diagnostics_url' => route('products.diagnostics', ['intelligence' => $filter]),
        ];
    }

    private function flag(string $filter, string $label, string $level, string $description): array
    {
        return [
            'filter' => $filter,
            'label' => $label,
            'level' => $level,
            'description' => $description,
            'url' => route('products.index', ['intelligence' => $filter]),
        ];
    }

    private function level(array $flags): string
    {
        $levels = collect($flags)->pluck('level');

        if ($levels->contains('danger')) {
            return 'danger';
        }

        if ($levels->contains('warning')) {
            return 'warning';
        }

        if ($levels->contains('info')) {
            return 'info';
        }

        return 'success';
    }

    private function labelForLevel(string $level): string
    {
        return match ($level) {
            'danger' => 'Critico',
            'warning' => 'Atencao',
            'info' => 'Melhorar',
            default => 'Ok',
        };
    }

    private function globalStatusLabel(?string $status): string
    {
        return match ($status) {
            GlobalProduct::STATUS_VERIFIED => 'Verificado',
            GlobalProduct::STATUS_CONFLICT => 'Conflito',
            GlobalProduct::STATUS_PENDING => 'Pendente',
            default => 'Sem vinculo',
        };
    }

    private function intelligenceOptions(): array
    {
        return [
            'linked' => 'Vinculados ao global',
            'unlinked' => 'Com EAN sem global',
            'without_gtin' => 'Sem EAN',
            'invalid_gtin' => 'EAN invalido',
            'without_price' => 'Sem preco',
            'without_image' => 'Sem imagem',
            'global_pending' => 'Global pendente',
            'global_conflict' => 'Conflito global',
            'low_stock' => 'Estoque baixo',
            'missing_category' => 'Sem categoria',
        ];
    }

    private function filterOptions(): array
    {
        return [
            'categories' => Product::query()
                ->select('category')
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->filter()
                ->values(),
            'brands' => Product::query()
                ->select('brand')
                ->whereNotNull('brand')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand')
                ->filter()
                ->values(),
        ];
    }

    private function activeFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q')),
            'intelligence' => $request->query('intelligence'),
            'category' => $request->query('category'),
            'brand' => $request->query('brand'),
            'status' => $request->query('status'),
        ];
    }

    private function gtin(Product $product): ?string
    {
        return Gtin::normalize($product->gtin ?: $product->barcode);
    }

    private function whereHasGtin(Builder $query): void
    {
        $query
            ->where(fn (Builder $builder) => $builder->whereNotNull('gtin')->where('gtin', '<>', ''))
            ->orWhere(fn (Builder $builder) => $builder->whereNotNull('barcode')->where('barcode', '<>', ''));
    }

    private function whereMissingGtin(Builder $query): void
    {
        $query
            ->where(fn (Builder $builder) => $builder->whereNull('gtin')->orWhere('gtin', ''))
            ->where(fn (Builder $builder) => $builder->whereNull('barcode')->orWhere('barcode', ''));
    }

    private function whereInvalidGtin(Builder $query): void
    {
        $ids = $this->invalidProductIds();

        if ($ids === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('id', $ids);
    }

    private function invalidProductIds(): array
    {
        return Product::query()
            ->select(['id', 'gtin', 'barcode'])
            ->get()
            ->filter(function (Product $product) {
                $gtin = $this->gtin($product);

                return $gtin !== null && ! Gtin::looksValid($gtin);
            })
            ->pluck('id')
            ->all();
    }
}
