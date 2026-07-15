<?php

namespace App\Modules\Products\Services;

use App\Core\Base\BaseService;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Services\ProductIntelligenceService;
use App\Modules\Products\Contracts\ProductRepositoryInterface;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\Gtin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductService extends BaseService
{
    public function __construct(
        ProductRepositoryInterface $repository,
        private readonly ProductLookupService $lookupService,
        private readonly ProductIntelligenceService $intelligence
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        /** @var Product $product */
        $product = $this->repository->create($this->prepareProductData($data));

        $this->lookupService->rememberProduct($product, $product->lookup_source ?: 'vetflow_manual');
        $this->attachGlobalProduct($product);

        return $product;
    }

    public function update(int $id, array $data): Model
    {
        /** @var Product $product */
        $product = $this->repository->findOrFail($id);

        $this->repository->update($product, $this->prepareProductData($data));

        $product->refresh();
        $this->lookupService->rememberProduct($product, $product->lookup_source ?: 'vetflow_manual');
        $this->attachGlobalProduct($product);

        return $product;
    }

    public function createFromGlobalProduct(GlobalProduct $globalProduct, array $overrides = []): Product
    {
        $data = array_merge([
            'clinic_id' => $overrides['clinic_id'] ?? null,
            'global_product_id' => $globalProduct->id,
            'gtin' => $globalProduct->gtin,
            'barcode' => $globalProduct->barcode ?: $globalProduct->gtin,
            'name' => $globalProduct->name ?: 'Produto '.$globalProduct->gtin,
            'category' => $globalProduct->category,
            'brand' => $globalProduct->brand,
            'manufacturer' => $globalProduct->manufacturer,
            'description' => $globalProduct->description,
            'weight' => $globalProduct->weight,
            'unit' => $globalProduct->unit ?: 'un',
            'image_path' => $globalProduct->image_path,
            'lookup_source' => $globalProduct->api_source ?: 'vetflow_global',
            'lookup_metadata' => array_merge($globalProduct->metadata ?? [], [
                'global_product_id' => $globalProduct->id,
                'global_status' => $globalProduct->status,
                'source_confidence' => (float) $globalProduct->source_confidence,
            ]),
            'looked_up_at' => now(),
            'cost_price' => $overrides['cost_price'] ?? 0,
            'sale_price' => $overrides['sale_price'] ?? 0,
            'stock_quantity' => $overrides['stock_quantity'] ?? 0,
            'minimum_stock' => $overrides['minimum_stock'] ?? 0,
            'active' => true,
        ], array_filter($overrides, fn ($value) => $value !== null && $value !== ''));

        /** @var Product $product */
        $product = $this->repository->create($this->prepareProductData($data));

        $product->forceFill(['global_product_id' => $globalProduct->id])->save();
        $this->lookupService->rememberProduct($product, 'vetflow_global');

        return $product->refresh();
    }

    public function linkGlobalProduct(Product $product): ?GlobalProduct
    {
        $gtin = Gtin::normalize($product->gtin ?: $product->barcode);

        if (! Gtin::looksValid($gtin)) {
            return null;
        }

        $global = $this->intelligence->globalProductForGtin($gtin);

        if (! $global) {
            $this->lookupService->rememberProduct($product, $product->lookup_source ?: 'vetflow_manual');
            $global = $this->intelligence->globalProductForGtin($gtin);
        }

        if (! $global) {
            return null;
        }

        if ((int) $product->global_product_id !== (int) $global->id) {
            $product->forceFill(['global_product_id' => $global->id])->save();
        }

        return $global;
    }

    public function enrichByGtin(int $id): ?Product
    {
        /** @var Product $product */
        $product = $this->repository->findOrFail($id);
        $gtin = Gtin::normalize($product->gtin ?: $product->barcode);

        if (! Gtin::looksValid($gtin)) {
            throw new InvalidArgumentException('Produto sem EAN/GTIN valido para consulta.');
        }

        $result = $this->lookupService->lookup($gtin);

        if (! $result?->hasUsefulData()) {
            return null;
        }

        $data = array_filter(
            $result->toProductAttributes(),
            fn ($value) => $value !== null && $value !== '' && $value !== []
        );

        $data['lookup_metadata'] = array_merge($product->lookup_metadata ?? [], $data['lookup_metadata'] ?? [], [
            'enriched_at' => now()->toDateTimeString(),
        ]);

        if ($globalProductId = $result->metadata['global_product_id'] ?? null) {
            $data['global_product_id'] = $globalProductId;
        }

        $this->repository->update($product, $this->prepareProductData($data));

        $product->refresh();
        $this->attachGlobalProduct($product);

        return $product->refresh();
    }

    public function syncFromGlobalProduct(int $id): ?Product
    {
        /** @var Product $product */
        $product = $this->repository->findOrFail($id);
        $global = $product->globalProduct ?: $this->linkGlobalProduct($product);

        if (! $global) {
            return null;
        }

        $data = array_filter([
            'global_product_id' => $global->id,
            'gtin' => $global->gtin ?: $product->gtin,
            'barcode' => $global->barcode ?: $global->gtin ?: $product->barcode,
            'name' => $global->name ?: $product->name,
            'brand' => $global->brand ?: $product->brand,
            'manufacturer' => $global->manufacturer ?: $product->manufacturer,
            'category' => $global->category ?: $product->category,
            'description' => $global->description ?: $product->description,
            'weight' => $global->weight ?: $product->weight,
            'unit' => $global->unit ?: $product->unit,
            'image_path' => $global->image_path ?: $product->image_path,
            'lookup_source' => $global->api_source ?: $product->lookup_source ?: 'vetflow_global',
            'lookup_metadata' => array_merge($product->lookup_metadata ?? [], $global->metadata ?? [], [
                'synced_from_global_product_id' => $global->id,
                'global_status' => $global->status,
                'source_confidence' => (float) $global->source_confidence,
                'synced_at' => now()->toDateTimeString(),
            ]),
            'looked_up_at' => now(),
        ], fn ($value) => $value !== null && $value !== '');

        $this->repository->update($product, $this->prepareProductData($data));

        return $product->refresh();
    }

    public function autoLinkLocalProducts(?int $limit = null): array
    {
        $query = Product::query()
            ->whereNull('global_product_id')
            ->where(function ($builder) {
                $builder
                    ->whereNotNull('gtin')
                    ->orWhereNotNull('barcode');
            })
            ->orderBy('id');

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        $processed = 0;
        $linked = 0;
        $skipped = 0;

        $query->get()->each(function (Product $product) use (&$processed, &$linked, &$skipped) {
            $processed++;

            if ($this->linkGlobalProduct($product)) {
                $linked++;
                return;
            }

            $skipped++;
        });

        return compact('processed', 'linked', 'skipped');
    }

    private function prepareProductData(array $data): array
    {
        $imageFile = $data['image_file'] ?? null;
        unset($data['image_file']);

        $gtin = Gtin::normalize($data['gtin'] ?? $data['barcode'] ?? null);

        if ($gtin) {
            $data['gtin'] = $gtin;
            $data['barcode'] = $gtin;
            $data['lookup_source'] = $data['lookup_source'] ?? 'vetflow_manual';
        }

        if (isset($data['lookup_metadata']) && is_string($data['lookup_metadata'])) {
            $decoded = json_decode($data['lookup_metadata'], true);
            $data['lookup_metadata'] = is_array($decoded) ? $decoded : null;
        }

        foreach (['cost_price', 'sale_price', 'stock_quantity', 'minimum_stock'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = 0;
            }
        }

        if (empty($data['unit'])) {
            $data['unit'] = 'un';
        }

        if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
            $data['image_path'] = $this->storeProductImage(
                $imageFile,
                $gtin ?: ($data['sku'] ?? $data['name'] ?? 'produto')
            );
            $data['lookup_source'] = ($data['lookup_source'] ?? null) ?: 'vetflow_manual';
        }

        return $data;
    }

    private function storeProductImage(UploadedFile $image, string $identifier): string
    {
        $baseName = Gtin::normalize($identifier) ?: Str::slug($identifier) ?: 'produto';
        $extension = $image->extension() ?: $image->guessExtension() ?: 'jpg';
        $filename = $baseName.'-'.Str::random(8).'.'.$extension;

        return $image->storeAs('products/manual', $filename, 'public');
    }

    private function attachGlobalProduct(Product $product): void
    {
        $global = $this->intelligence->globalProductForGtin($product->gtin ?: $product->barcode);

        if (! $global || (int) $product->global_product_id === (int) $global->id) {
            return;
        }

        $product->forceFill(['global_product_id' => $global->id])->save();
    }
}
