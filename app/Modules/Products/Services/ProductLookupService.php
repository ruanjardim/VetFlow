<?php

namespace App\Modules\Products\Services;

use App\Modules\ProductIntelligence\Services\ProductIntelligenceService;
use App\Modules\Products\Data\ProductLookupOutcome;
use App\Modules\Products\Data\ProductLookupResult;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductLookupCatalog;
use Throwable;

class ProductLookupService
{
    public function __construct(private readonly ProductIntelligenceService $intelligence) {}

    public function lookup(string $gtin): ?ProductLookupResult
    {
        return $this->lookupOutcome($gtin)->result;
    }

    public function lookupOutcome(string $gtin): ProductLookupOutcome
    {
        $outcome = $this->intelligence->lookupOutcome($gtin);
        $result = $outcome->result;

        if ($result?->hasUsefulData()) {
            $this->rememberFound($result);
        }

        return $outcome;
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
}
