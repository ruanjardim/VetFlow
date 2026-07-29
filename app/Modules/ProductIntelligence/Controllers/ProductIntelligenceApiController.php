<?php

namespace App\Modules\ProductIntelligence\Controllers;

use App\Modules\Dashboard\Services\DashboardProductIntelligenceService;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Services\GlobalProductCatalogService;
use App\Modules\Products\Services\ProductIntelligenceAuditService;
use App\Modules\Products\Services\ProductLookupService;
use Illuminate\Http\JsonResponse;

class ProductIntelligenceApiController
{
    public function metrics(
        ProductIntelligenceAuditService $auditService,
        DashboardProductIntelligenceService $dashboardProductIntelligenceService
    ): JsonResponse {
        $summary = $dashboardProductIntelligenceService->summary();

        return response()->json([
            'ok' => true,
            'local_products' => $auditService->stats(),
            'global_catalog' => $summary['stats'] ?? [],
            'health' => $summary['health'] ?? null,
            'actions' => $summary['actions'] ?? [],
        ]);
    }

    public function lookup(string $gtin, ProductLookupService $lookupService): JsonResponse
    {
        $outcome = $lookupService->lookupOutcome($gtin);
        $result = $outcome->result;

        if ($outcome->unavailable()) {
            return response()->json([
                'ok' => false,
                'found' => false,
                'manual_allowed' => true,
                'lookup_status' => $outcome->status,
                'retryable' => true,
                'message' => 'As bases externas de produtos estao temporariamente indisponiveis.',
            ], 503);
        }

        if (! $result) {
            return response()->json([
                'ok' => true,
                'found' => false,
                'manual_allowed' => true,
                'lookup_status' => $outcome->status,
                'cached' => $outcome->cached,
                'message' => 'Produto nao encontrado nas bases conectadas.',
            ]);
        }

        $product = array_filter(
            $result->toProductAttributes(),
            fn ($value) => $value !== null && $value !== ''
        );

        if ($result->imagePath) {
            $product['image_preview_url'] = route('products.lookup-image', [
                'filename' => basename($result->imagePath),
            ]);
        }

        return response()->json([
            'ok' => true,
            'found' => true,
            'source' => $result->source,
            'global_product_id' => $result->metadata['global_product_id'] ?? null,
            'status' => $result->metadata['status'] ?? null,
            'source_confidence' => $result->metadata['source_confidence'] ?? null,
            'product' => $product,
        ]);
    }

    public function globalProduct(GlobalProduct $globalProduct, GlobalProductCatalogService $catalog): JsonResponse
    {
        $globalProduct->load([
            'sources' => fn ($query) => $query->latest('queried_at')->limit(10),
            'images' => fn ($query) => $query->orderByDesc('is_primary')->latest()->limit(10),
            'regulatoryData' => fn ($query) => $query->latest()->limit(10),
        ]);

        $product = $catalog->decorate($globalProduct);

        return response()->json([
            'ok' => true,
            'product' => [
                'id' => $product->id,
                'gtin' => $product->gtin,
                'ean' => $product->ean,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'brand' => $product->brand,
                'manufacturer' => $product->manufacturer,
                'category' => $product->category,
                'subcategory' => $product->subcategory,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'image_path' => $product->image_path,
                'weight' => $product->weight,
                'unit' => $product->unit,
                'package' => $product->package,
                'species' => $product->species,
                'active_ingredient' => $product->active_ingredient,
                'dosage' => $product->dosage,
                'registration_number' => $product->registration_number,
                'api_source' => $product->api_source,
                'source_confidence' => (float) $product->source_confidence,
                'status' => $product->status,
                'quality_score' => $product->quality_score,
                'quality_status' => $product->quality_status,
                'quality_flags' => $product->quality_flags,
                'last_lookup_at' => optional($product->last_lookup_at)->toDateTimeString(),
            ],
            'sources' => $product->sources->map(fn ($source) => [
                'name' => $source->source_name,
                'label' => $source->source_label,
                'type' => $source->source_type,
                'confidence' => (float) $source->confidence,
                'queried_at' => optional($source->queried_at)->toDateTimeString(),
            ])->values(),
            'images' => $product->images->map(fn ($image) => [
                'url' => $image->image_url,
                'path' => $image->image_path,
                'type' => $image->image_type,
                'source' => $image->source_name,
                'confidence' => (float) $image->confidence,
                'primary' => (bool) $image->is_primary,
            ])->values(),
            'regulatory_data' => $product->regulatoryData->map(fn ($data) => [
                'registration_number' => $data->registration_number,
                'authority' => $data->authority,
                'country' => $data->country,
                'active_ingredient' => $data->active_ingredient,
                'dosage' => $data->dosage,
                'concentration' => $data->concentration,
                'pharmaceutical_form' => $data->pharmaceutical_form,
                'storage_temperature' => $data->storage_temperature,
                'prescription_required' => $data->prescription_required,
                'source' => $data->source_name,
                'confidence' => (float) $data->confidence,
            ])->values(),
        ]);
    }
}
