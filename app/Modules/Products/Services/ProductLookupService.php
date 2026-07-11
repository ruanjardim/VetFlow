<?php

namespace App\Modules\Products\Services;

use App\Modules\ProductIntelligence\Services\ProductIntelligenceService;
use App\Modules\Products\Data\ProductLookupResult;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductLookupCatalog;
use Throwable;

class ProductLookupService
{
    public function __construct(private readonly ProductIntelligenceService $intelligence)
    {
    }

    public function lookup(string $gtin): ?ProductLookupResult
    {
        $result = $this->intelligence->lookup($gtin);

        if ($result?->hasUsefulData()) {
            $this->rememberFound($result);
        }

        return $result;
    }

    public function rememberProduct(Product $product, string $source = 'vetflow_manual'): ?ProductLookupResult
    {
        $result = $this->intelligence->rememberProduct($product, $source);

        if ($result?->hasUsefulData()) {
            $this->rememberFound($result);
        }

        return $result;
    }

    private function rememberFound(ProductLookupResult $result): void
    {
        try {
            ProductLookupCatalog::query()->updateOrCreate(
                ['gtin' => $result->gtin],
                $result->toCatalogAttributes('found')
            );
        } catch (Throwable) {
            //
        }
    }

    private function rememberMiss(string $gtin): void
    {
        try {
            ProductLookupCatalog::query()->updateOrCreate(
                ['gtin' => $gtin],
                [
                    'lookup_status' => 'not_found',
                    'source' => 'external_lookup',
                    'last_lookup_at' => now(),
                    'failed_at' => now(),
                ]
            );
        } catch (Throwable) {
            //
        }
    }
}
