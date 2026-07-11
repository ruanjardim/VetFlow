<?php

namespace App\Modules\ProductIntelligence\Services;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductImage;
use App\Modules\ProductIntelligence\Models\GlobalProductRegulatoryData;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\ProductIntelligence\Models\GlobalProductSource;
use App\Modules\Products\Data\ProductLookupResult;
use App\Modules\Products\LookupProviders\CommercialGtinJsonProvider;
use App\Modules\Products\LookupProviders\OpenFoodFactsFamilyProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\Gtin;
use App\Modules\Products\Support\ProductLookupImageDownloader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductIntelligenceService
{
    private const LAYERS = ['free', 'commercial', 'official'];

    public function __construct(private readonly ProductLookupImageDownloader $imageDownloader)
    {
    }

    public function lookup(string $gtin): ?ProductLookupResult
    {
        $normalized = Gtin::normalize($gtin);

        if (! Gtin::looksValid($normalized)) {
            return null;
        }

        $variants = Gtin::variants($normalized);

        if ($global = $this->lookupGlobalCatalog($variants)) {
            return $this->resultFromGlobalProduct($global);
        }

        if ($product = $this->lookupExistingClinicProduct($variants)) {
            return $this->rememberProduct($product, 'vetflow_product');
        }

        if (! config('product_lookup.enabled', true)) {
            return null;
        }

        foreach (self::LAYERS as $layer) {
            $results = $this->lookupExternalLayer($layer, $variants);

            if ($results->isEmpty()) {
                continue;
            }

            $result = $this->consolidateResults($normalized, $results);

            if (! $result?->hasUsefulData()) {
                continue;
            }

            $this->rememberFound($result, $results);

            return $result;
        }

        $this->rememberMiss($normalized);

        return null;
    }

    public function rememberProduct(Product $product, string $source = 'vetflow_manual'): ?ProductLookupResult
    {
        $gtin = Gtin::normalize($product->gtin ?: $product->barcode);

        if (! Gtin::looksValid($gtin)) {
            return null;
        }

        $result = new ProductLookupResult(
            gtin: $gtin,
            name: $product->name,
            brand: $product->brand,
            category: $product->category,
            description: $product->description,
            manufacturer: $product->manufacturer,
            unit: $product->unit,
            weight: $product->weight,
            imagePath: $product->image_path,
            source: $product->lookup_source ?: $source,
            metadata: $product->lookup_metadata ?? []
        );

        $this->rememberFound($result, collect([$result]), GlobalProduct::STATUS_PENDING);

        return $result;
    }

    public function globalProductForGtin(?string $gtin): ?GlobalProduct
    {
        $normalized = Gtin::normalize($gtin);

        if (! Gtin::looksValid($normalized)) {
            return null;
        }

        return $this->lookupGlobalCatalog(Gtin::variants($normalized));
    }

    private function lookupGlobalCatalog(array $gtins): ?GlobalProduct
    {
        if (! $this->catalogReady()) {
            return null;
        }

        return GlobalProduct::query()
            ->where(function ($query) use ($gtins) {
                $query
                    ->whereIn('gtin', $gtins)
                    ->orWhereIn('ean', $gtins)
                    ->orWhereIn('barcode', $gtins);
            })
            ->orderByRaw("CASE status WHEN 'VERIFIED' THEN 0 WHEN 'PENDING' THEN 1 ELSE 2 END")
            ->orderByDesc('source_confidence')
            ->first();
    }

    private function lookupExistingClinicProduct(array $gtins): ?Product
    {
        return Product::query()
            ->where(function ($query) use ($gtins) {
                $query
                    ->whereIn('gtin', $gtins)
                    ->orWhereIn('barcode', $gtins);
            })
            ->orderByDesc('updated_at')
            ->first();
    }

    private function lookupExternalLayer(string $layer, array $gtins): Collection
    {
        return $this->externalProviders()
            ->filter(fn (array $entry) => $entry['tier'] === $layer)
            ->flatMap(function (array $entry) use ($gtins) {
                $found = [];

                foreach ($gtins as $candidate) {
                    $result = $entry['provider']->lookup($candidate);

                    if (! $result?->hasUsefulData()) {
                        continue;
                    }

                    if ($result->imageUrl && ! $result->imagePath) {
                        $result = $result->withImagePath(
                            $this->imageDownloader->download($result->imageUrl, $candidate, (string) $result->source)
                        );
                    }

                    $found[] = $this->withIntelligenceMetadata($result, $entry);
                    break;
                }

                return $found;
            })
            ->values();
    }

    private function externalProviders(): Collection
    {
        return collect(config('product_lookup.providers', []))
            ->filter(fn (array $provider) => (bool) ($provider['enabled'] ?? false))
            ->sortBy(fn (array $provider) => (int) ($provider['priority'] ?? 100))
            ->map(function (array $provider) {
                $instance = match ($provider['driver'] ?? null) {
                    'commercial_gtin_json' => new CommercialGtinJsonProvider($provider),
                    'open_food_facts_family' => new OpenFoodFactsFamilyProvider($provider),
                    default => null,
                };

                if (! $instance) {
                    return null;
                }

                return [
                    'config' => $provider,
                    'provider' => $instance,
                    'tier' => $this->providerTier($provider),
                    'confidence' => (float) ($provider['confidence'] ?? $this->defaultConfidence($provider)),
                ];
            })
            ->filter()
            ->values();
    }

    private function providerTier(array $provider): string
    {
        if (! empty($provider['tier'])) {
            return (string) $provider['tier'];
        }

        return ($provider['driver'] ?? null) === 'commercial_gtin_json' ? 'commercial' : 'free';
    }

    private function defaultConfidence(array $provider): float
    {
        return match ($this->providerTier($provider)) {
            'official' => 95,
            'commercial' => 85,
            default => 65,
        };
    }

    private function withIntelligenceMetadata(ProductLookupResult $result, array $entry): ProductLookupResult
    {
        return new ProductLookupResult(
            gtin: $result->gtin,
            name: $result->name,
            brand: $result->brand,
            category: $result->category,
            description: $result->description,
            manufacturer: $result->manufacturer,
            unit: $result->unit,
            weight: $result->weight,
            imageUrl: $result->imageUrl,
            imagePath: $result->imagePath,
            source: $result->source,
            metadata: array_merge($result->metadata, [
                'source_tier' => $entry['tier'],
                'source_confidence' => $entry['confidence'],
                'provider_name' => $entry['config']['name'] ?? $result->source,
                'provider_label' => $entry['config']['label'] ?? ($result->metadata['provider_label'] ?? null),
            ]),
            sourcePayload: $result->sourcePayload
        );
    }

    private function consolidateResults(string $gtin, Collection $results): ?ProductLookupResult
    {
        if ($results->isEmpty()) {
            return null;
        }

        $best = $results->sortByDesc(fn (ProductLookupResult $result) => (float) ($result->metadata['source_confidence'] ?? 0))->first();

        return new ProductLookupResult(
            gtin: $gtin,
            name: $this->firstFilled($results, 'name') ?: $best->name,
            brand: $this->firstFilled($results, 'brand') ?: $best->brand,
            category: $this->firstFilled($results, 'category') ?: $best->category,
            description: $this->firstFilled($results, 'description') ?: $best->description,
            manufacturer: $this->firstFilled($results, 'manufacturer') ?: $best->manufacturer,
            unit: $this->firstFilled($results, 'unit') ?: $best->unit,
            weight: $this->firstFilled($results, 'weight') ?: $best->weight,
            imageUrl: $this->firstFilled($results, 'imageUrl') ?: $best->imageUrl,
            imagePath: $this->firstFilled($results, 'imagePath') ?: $best->imagePath,
            source: $best->source,
            metadata: [
                'sources' => $results->map(fn (ProductLookupResult $result) => [
                    'source' => $result->source,
                    'tier' => $result->metadata['source_tier'] ?? null,
                    'confidence' => $result->metadata['source_confidence'] ?? null,
                ])->values()->all(),
                'source_confidence' => $results->max(fn (ProductLookupResult $result) => (float) ($result->metadata['source_confidence'] ?? 0)),
            ],
            sourcePayload: $best->sourcePayload
        );
    }

    private function firstFilled(Collection $results, string $property): ?string
    {
        foreach ($results as $result) {
            $value = $result->{$property};

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function rememberFound(ProductLookupResult $result, Collection $sources, string $status = GlobalProduct::STATUS_PENDING): ?GlobalProduct
    {
        if (! $this->catalogReady()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($result, $sources, $status) {
                $existing = GlobalProduct::query()->where('gtin', $result->gtin)->first();
                $status = $this->resolveStatus($existing, $result, $status);

                $global = GlobalProduct::query()->updateOrCreate(
                    ['gtin' => $result->gtin],
                    [
                        'ean' => $result->gtin,
                        'barcode' => $result->gtin,
                        'name' => $result->name,
                        'brand' => $result->brand,
                        'manufacturer' => $result->manufacturer,
                        'category' => $result->category,
                        'description' => $result->description,
                        'image_url' => $result->imageUrl,
                        'image_path' => $result->imagePath,
                        'weight' => $result->weight,
                        'unit' => $result->unit,
                        'package' => $result->metadata['packaging'] ?? $result->metadata['quantity'] ?? null,
                        'api_source' => $result->source,
                        'source_confidence' => (float) ($result->metadata['source_confidence'] ?? 70),
                        'status' => $status,
                        'metadata' => $result->metadata,
                        'last_lookup_at' => now(),
                    ]
                );

                $this->rememberSources($global, $sources);
                $this->rememberImage($global, $result);
                $this->rememberRegulatoryData($global, $result);

                return $global;
            });
        } catch (Throwable) {
            return null;
        }
    }

    private function rememberSources(GlobalProduct $global, Collection $sources): void
    {
        foreach ($sources as $source) {
            GlobalProductSource::query()->updateOrCreate(
                [
                    'global_product_id' => $global->id,
                    'source_name' => $source->source ?: 'vetflow',
                ],
                [
                    'source_label' => $source->metadata['provider_label'] ?? $source->source,
                    'source_type' => $source->metadata['source_tier'] ?? 'manual',
                    'confidence' => (float) ($source->metadata['source_confidence'] ?? 70),
                    'status' => GlobalProduct::STATUS_PENDING,
                    'queried_at' => now(),
                    'payload' => $source->sourcePayload ?: $source->metadata,
                ]
            );
        }
    }

    private function rememberImage(GlobalProduct $global, ProductLookupResult $result): void
    {
        if (! $result->imageUrl && ! $result->imagePath) {
            return;
        }

        GlobalProductImage::query()->updateOrCreate(
            [
                'global_product_id' => $global->id,
                'image_url' => $result->imageUrl,
            ],
            [
                'image_path' => $result->imagePath,
                'image_type' => 'front',
                'source_name' => $result->source,
                'confidence' => (float) ($result->metadata['source_confidence'] ?? 70),
                'is_primary' => true,
            ]
        );
    }

    private function rememberRegulatoryData(GlobalProduct $global, ProductLookupResult $result): void
    {
        $fields = [
            'registration_number',
            'active_ingredient',
            'dosage',
            'concentration',
            'pharmaceutical_form',
            'storage_temperature',
            'prescription_required',
        ];

        $metadata = collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $result->metadata[$field] ?? null])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if ($metadata === []) {
            return;
        }

        GlobalProductRegulatoryData::query()->updateOrCreate(
            [
                'global_product_id' => $global->id,
                'source_name' => $result->source,
                'registration_number' => $metadata['registration_number'] ?? null,
            ],
            array_merge($metadata, [
                'country' => $result->metadata['country'] ?? null,
                'confidence' => (float) ($result->metadata['source_confidence'] ?? 70),
                'payload' => $result->metadata,
            ])
        );
    }

    private function rememberMiss(string $gtin): void
    {
        if (! Schema::hasTable('global_product_suggestions')) {
            return;
        }

        try {
            GlobalProductSuggestion::query()->updateOrCreate(
                [
                    'gtin' => $gtin,
                    'suggestion_type' => 'not_found',
                ],
                [
                    'source_name' => 'product_intelligence',
                    'status' => GlobalProduct::STATUS_PENDING,
                    'confidence' => 0,
                    'payload' => [
                        'message' => 'Nenhuma fonte retornou dados uteis para este GTIN.',
                    ],
                ]
            );
        } catch (Throwable) {
            //
        }
    }

    private function resolveStatus(?GlobalProduct $existing, ProductLookupResult $result, string $newStatus): string
    {
        if (! $existing) {
            return $newStatus;
        }

        foreach (['name', 'brand', 'manufacturer'] as $field) {
            if ($this->hasConflict($existing->{$field}, $result->{$field})) {
                return GlobalProduct::STATUS_CONFLICT;
            }
        }

        if ($existing->status === GlobalProduct::STATUS_VERIFIED && $newStatus === GlobalProduct::STATUS_PENDING) {
            return GlobalProduct::STATUS_VERIFIED;
        }

        return $newStatus;
    }

    private function hasConflict(?string $existing, ?string $incoming): bool
    {
        if (! $existing || ! $incoming) {
            return false;
        }

        return $this->normalizeComparison($existing) !== $this->normalizeComparison($incoming);
    }

    private function normalizeComparison(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private function catalogReady(): bool
    {
        return Schema::hasTable('global_products');
    }

    private function resultFromGlobalProduct(GlobalProduct $global): ProductLookupResult
    {
        return new ProductLookupResult(
            gtin: $global->gtin,
            name: $global->name,
            brand: $global->brand,
            category: $global->category,
            description: $global->description,
            manufacturer: $global->manufacturer,
            unit: $global->unit,
            weight: $global->weight,
            imageUrl: $global->image_url,
            imagePath: $global->image_path,
            source: $global->api_source ?: 'vetflow_global',
            metadata: array_merge($global->metadata ?? [], [
                'global_product_id' => $global->id,
                'status' => $global->status,
                'source_confidence' => (float) $global->source_confidence,
            ])
        );
    }
}
