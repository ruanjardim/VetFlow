<?php

namespace App\Modules\Products\Services;

use App\Core\Base\BaseService;
use App\Modules\ProductIntelligence\Services\ProductIntelligenceService;
use App\Modules\Products\Contracts\ProductRepositoryInterface;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\Gtin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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
