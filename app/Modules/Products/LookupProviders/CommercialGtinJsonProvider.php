<?php

namespace App\Modules\Products\LookupProviders;

use App\Modules\Products\Contracts\ProductLookupProviderInterface;
use App\Modules\Products\Data\ProductLookupResult;
use App\Modules\Products\Exceptions\ProductLookupProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CommercialGtinJsonProvider implements ProductLookupProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function lookup(string $gtin): ?ProductLookupResult
    {
        $baseUrl = trim((string) ($this->config['base_url'] ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        try {
            $request = Http::acceptJson()
                ->timeout((int) config('product_lookup.timeout_seconds', 4))
                ->connectTimeout((int) config('product_lookup.connect_timeout_seconds', 2))
                ->withHeaders([
                    'User-Agent' => config('product_lookup.user_agent'),
                ]);

            if (! empty($this->config['token'])) {
                $request = $this->authorize($request, (string) $this->config['token']);
            }

            $response = $this->request($request, rtrim($baseUrl, '/').'/'.rawurlencode($gtin));

            if ($response->status() === 404) {
                return null;
            }

            if (! $response->successful()) {
                throw new ProductLookupProviderException(
                    'O provedor externo respondeu com erro.',
                    $response->status()
                );
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new ProductLookupProviderException('O provedor externo retornou uma resposta invalida.');
            }

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
        } catch (ProductLookupProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ProductLookupProviderException(
                'Nao foi possivel conectar ao provedor externo.',
                previous: $exception
            );
        }
    }

    private function request(PendingRequest $request, string $url): Response
    {
        $attempts = max(1, min(3, (int) config('product_lookup.attempts', 2)));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $request->get($url);

                if (! in_array($response->status(), [429, 500, 502, 503, 504], true) || $attempt === $attempts) {
                    return $response;
                }
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt === $attempts) {
                    throw $exception;
                }
            }
        }

        throw $lastException ?? new ProductLookupProviderException('Nao foi possivel consultar o provedor externo.');
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
