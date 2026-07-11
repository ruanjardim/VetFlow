<?php

namespace App\Modules\Products\LookupProviders;

use App\Modules\Products\Contracts\ProductLookupProviderInterface;
use App\Modules\Products\Data\ProductLookupResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class CommercialGtinJsonProvider implements ProductLookupProviderInterface
{
    public function __construct(private readonly array $config)
    {
    }

    public function lookup(string $gtin): ?ProductLookupResult
    {
        $baseUrl = trim((string) ($this->config['base_url'] ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        try {
            $request = Http::acceptJson()
                ->timeout((int) config('product_lookup.timeout_seconds', 4))
                ->withHeaders([
                    'User-Agent' => config('product_lookup.user_agent'),
                ]);

            if (! empty($this->config['token'])) {
                $request = $this->authorize($request, (string) $this->config['token']);
            }

            $response = $request->get(rtrim($baseUrl, '/').'/'.rawurlencode($gtin));

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json() ?? [];
            $product = data_get($payload, 'product', $payload);

            if (! is_array($product)) {
                return null;
            }

            return new ProductLookupResult(
                gtin: $gtin,
                name: data_get($product, 'name') ?: data_get($product, 'description'),
                brand: data_get($product, 'brand'),
                category: data_get($product, 'category'),
                description: data_get($product, 'description') ?: data_get($product, 'long_description'),
                manufacturer: data_get($product, 'manufacturer'),
                unit: data_get($product, 'unit'),
                weight: data_get($product, 'weight') ?: data_get($product, 'quantity'),
                imageUrl: data_get($product, 'image_url') ?: data_get($product, 'image'),
                source: $this->config['name'] ?? 'commercial_gtin_json',
                metadata: [
                    'provider_label' => $this->config['label'] ?? null,
                ],
                sourcePayload: $payload
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function authorize(PendingRequest $request, string $token): PendingRequest
    {
        return match ($this->config['auth'] ?? 'bearer') {
            'query_key' => $request->withQueryParameters([
                $this->config['query_key_name'] ?? 'api_key' => $token,
            ]),
            default => $request->withToken($token),
        };
    }
}
