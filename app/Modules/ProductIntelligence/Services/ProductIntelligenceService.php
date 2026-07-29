<?php

namespace App\Modules\ProductIntelligence\Services;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductImage;
use App\Modules\ProductIntelligence\Models\GlobalProductRegulatoryData;
use App\Modules\ProductIntelligence\Models\GlobalProductSource;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\Products\Data\ProductLookupOutcome;
use App\Modules\Products\Data\ProductLookupResult;
use App\Modules\Products\Exceptions\ProductLookupProviderException;
use App\Modules\Products\LookupProviders\CommercialGtinJsonProvider;
use App\Modules\Products\LookupProviders\OpenFoodFactsFamilyProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductLookupCatalog;
use App\Modules\Products\Support\Gtin;
use App\Modules\Products\Support\ProductLookupImageDownloader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductIntelligenceService
{
    private const LAYERS = ['free', 'commercial', 'official'];

    public function __construct(private readonly ProductLookupImageDownloader $imageDownloader) {}

    public function lookup(string $gtin): ?ProductLookupResult
    {
        return $this->lookupOutcome($gtin)->result;
    }

    public function lookupOutcome(string $gtin): ProductLookupOutcome
    {
        $normalized = Gtin::normalize($gtin);

        if (! Gtin::looksValid($normalized)) {
            return new ProductLookupOutcome(ProductLookupOutcome::INVALID);
        }

        $variants = Gtin::variants($normalized);

        if ($global = $this->lookupGlobalCatalog($variants)) {
            return new ProductLookupOutcome(
                ProductLookupOutcome::FOUND,
                $this->resultFromGlobalProduct($global)
            );
        }

        if ($product = $this->lookupExistingClinicProduct($variants)) {
            return new ProductLookupOutcome(
                ProductLookupOutcome::FOUND,
                $this->rememberProduct($product, 'vetflow_product')
            );
        }

        if (! config('product_lookup.enabled', true)) {
            return new ProductLookupOutcome(ProductLookupOutcome::DISABLED);
        }

        if ($this->hasRecentNegativeCache($variants)) {
            return new ProductLookupOutcome(
                ProductLookupOutcome::NOT_FOUND,
                cached: true
            );
        }

        $diagnostics = [];

        foreach (self::LAYERS as $layer) {
            $results = $this->lookupExternalLayer($layer, $variants, $diagnostics);

            if ($results->isEmpty()) {
                continue;
            }

            $result = $this->consolidateResults($normalized, $results);

            if (! $result?->hasUsefulData()) {
                continue;
            }

            $this->rememberFound($result, $results);

            return new ProductLookupOutcome(
                ProductLookupOutcome::FOUND,
                $result,
                $diagnostics
            );
        }

        if ($diagnostics === []) {
            return new ProductLookupOutcome(ProductLookupOutcome::DISABLED);
        }

        if ($this->allAttemptedProvidersUnavailable($diagnostics)) {
            return new ProductLookupOutcome(
                ProductLookupOutcome::UNAVAILABLE,
                diagnostics: $diagnostics
            );
        }

        $this->rememberMiss($normalized, $diagnostics);

        return new ProductLookupOutcome(
            ProductLookupOutcome::NOT_FOUND,
            diagnostics: $diagnostics
        );
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

    public function enrichGlobalProduct(GlobalProduct $globalProduct): ?GlobalProduct
    {
        $normalized = Gtin::normalize($globalProduct->gtin ?: $globalProduct->ean ?: $globalProduct->barcode);

        if (! Gtin::looksValid($normalized)) {
            return null;
        }

        $diagnostics = [];

        foreach (self::LAYERS as $layer) {
            $results = $this->lookupExternalLayer($layer, Gtin::variants($normalized), $diagnostics);

            if ($results->isEmpty()) {
                continue;
            }

            $result = $this->consolidateResults($normalized, $results);

            if (! $result?->hasUsefulData()) {
                continue;
            }

            return $this->rememberFound($result, $results, $globalProduct->status);
        }

        $metadata = $globalProduct->metadata ?? [];
        $metadata['last_enrichment_attempt'] = [
            'attempted_at' => now()->toDateTimeString(),
            'result' => $this->allAttemptedProvidersUnavailable($diagnostics)
                ? ProductLookupOutcome::UNAVAILABLE
                : ProductLookupOutcome::NOT_FOUND,
            'providers' => $diagnostics,
        ];

        $globalProduct->update([
            'metadata' => $metadata,
            'last_lookup_at' => now(),
        ]);

        return null;
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

    private function lookupExternalLayer(string $layer, array $gtins, array &$diagnostics): Collection
    {
        return $this->externalProviders()
            ->filter(fn (array $entry) => $entry['tier'] === $layer)
            ->flatMap(function (array $entry) use ($gtins, &$diagnostics) {
                $found = [];
                $providerName = (string) ($entry['config']['name'] ?? 'external_provider');
                $diagnostic = [
                    'provider' => $providerName,
                    'tier' => $entry['tier'],
                    'status' => ProductLookupOutcome::NOT_FOUND,
                ];

                foreach ($gtins as $candidate) {
                    try {
                        $result = $entry['provider']->lookup($candidate);
                    } catch (ProductLookupProviderException $exception) {
                        $diagnostic['status'] = ProductLookupOutcome::UNAVAILABLE;
                        $diagnostic['http_status'] = $exception->httpStatus();
                        $diagnostics[] = array_filter(
                            $diagnostic,
                            fn ($value) => $value !== null
                        );

                        Log::warning('Provedor de consulta de produtos indisponivel.', [
                            'provider' => $providerName,
                            'tier' => $entry['tier'],
                            'http_status' => $exception->httpStatus(),
                            'exception' => $exception::class,
                        ]);

                        return [];
                    } catch (Throwable $exception) {
                        $diagnostic['status'] = ProductLookupOutcome::UNAVAILABLE;
                        $diagnostics[] = $diagnostic;

                        Log::warning('Falha inesperada em provedor de consulta de produtos.', [
                            'provider' => $providerName,
                            'tier' => $entry['tier'],
                            'exception' => $exception::class,
                        ]);

                        return [];
                    }

                    if (! $result?->hasUsefulData()) {
                        continue;
                    }

                    if ($result->imageUrl && ! $result->imagePath) {
                        $result = $result->withImagePath(
                            $this->imageDownloader->download($result->imageUrl, $candidate, (string) $result->source)
                        );
                    }

                    $found[] = $this->withIntelligenceMetadata($result, $entry);
                    $diagnostic['status'] = ProductLookupOutcome::FOUND;
                    $diagnostics[] = $diagnostic;
                    break;
                }

                if ($found === []) {
                    $diagnostics[] = $diagnostic;
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
                        'name' => $result->name ?: $existing?->name,
                        'brand' => $result->brand ?: $existing?->brand,
                        'manufacturer' => $result->manufacturer ?: $existing?->manufacturer,
                        'category' => $result->category ?: $existing?->category,
                        'description' => $result->description ?: $existing?->description,
                        'image_url' => $result->imageUrl ?: $existing?->image_url,
                        'image_path' => $result->imagePath ?: $existing?->image_path,
                        'weight' => $result->weight ?: $existing?->weight,
                        'unit' => $result->unit ?: $existing?->unit,
                        'package' => $result->metadata['packaging'] ?? $result->metadata['quantity'] ?? $existing?->package,
                        'api_source' => $result->source,
                        'source_confidence' => max((float) ($existing?->source_confidence ?? 0), (float) ($result->metadata['source_confidence'] ?? 70)),
                        'status' => $status,
                        'metadata' => array_merge($existing?->metadata ?? [], $result->metadata),
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

    private function rememberMiss(string $gtin, array $diagnostics = []): void
    {
        if (Schema::hasTable('product_lookup_catalogs')) {
            try {
                ProductLookupCatalog::query()->updateOrCreate(
                    ['gtin' => $gtin],
                    [
                        'lookup_status' => ProductLookupOutcome::NOT_FOUND,
                        'source' => 'external_lookup',
                        'metadata' => ['providers' => $diagnostics],
                        'last_lookup_at' => now(),
                        'failed_at' => now(),
                    ]
                );
            } catch (Throwable) {
                //
            }
        }

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
                        'providers' => $diagnostics,
                    ],
                ]
            );
        } catch (Throwable) {
            //
        }
    }

    private function hasRecentNegativeCache(array $gtins): bool
    {
        if (! Schema::hasTable('product_lookup_catalogs')) {
            return false;
        }

        $days = max(0, (int) config('product_lookup.negative_cache_days', 7));

        if ($days === 0) {
            return false;
        }

        try {
            return ProductLookupCatalog::query()
                ->whereIn('gtin', $gtins)
                ->where('lookup_status', ProductLookupOutcome::NOT_FOUND)
                ->where('failed_at', '>=', now()->subDays($days))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function allAttemptedProvidersUnavailable(array $diagnostics): bool
    {
        return $diagnostics !== []
            && collect($diagnostics)->every(
                fn (array $diagnostic) => ($diagnostic['status'] ?? null) === ProductLookupOutcome::UNAVAILABLE
            );
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
