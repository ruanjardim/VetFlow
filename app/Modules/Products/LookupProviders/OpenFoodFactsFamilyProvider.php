<?php

namespace App\Modules\Products\LookupProviders;

use App\Modules\Products\Contracts\ProductLookupProviderInterface;
use App\Modules\Products\Data\ProductLookupResult;
use App\Modules\Products\Exceptions\ProductLookupProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenFoodFactsFamilyProvider implements ProductLookupProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function lookup(string $gtin): ?ProductLookupResult
    {
        try {
            $request = Http::acceptJson()
                ->timeout((int) config('product_lookup.timeout_seconds', 4))
                ->connectTimeout((int) config('product_lookup.connect_timeout_seconds', 2))
                ->withHeaders([
                    'User-Agent' => config('product_lookup.user_agent'),
                ]);
            $response = $this->request(
                $request,
                rtrim($this->config['base_url'], '/').'/api/v3.6/product/'.rawurlencode($gtin).'.json'
            );

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

            $product = data_get($payload, 'product');

            if (! is_array($product) || ! $this->wasFound($payload)) {
                return null;
            }

            $name = $this->firstFilled([
                data_get($product, 'product_name_pt'),
                data_get($product, 'product_name'),
                data_get($product, 'generic_name_pt'),
                data_get($product, 'generic_name'),
            ]);

            $category = $this->category($product);
            $quantity = $this->firstFilled([
                data_get($product, 'quantity'),
                trim((string) data_get($product, 'product_quantity').' '.(string) data_get($product, 'product_quantity_unit')),
            ]);

            return new ProductLookupResult(
                gtin: $gtin,
                name: $name,
                brand: $this->firstTagValue(data_get($product, 'brands')),
                category: $category,
                description: $this->firstFilled([
                    data_get($product, 'generic_name_pt'),
                    data_get($product, 'generic_name'),
                    data_get($product, 'ingredients_text_pt'),
                    data_get($product, 'ingredients_text'),
                ]),
                manufacturer: $this->firstFilled([
                    $this->firstTagValue(data_get($product, 'manufacturing_places')),
                    data_get($product, 'owner'),
                ]),
                unit: $this->unitFromQuantity($quantity),
                weight: $quantity,
                imageUrl: $this->firstFilled([
                    data_get($product, 'image_front_url'),
                    data_get($product, 'image_url'),
                    data_get($product, 'selected_images.front.display.pt'),
                    data_get($product, 'selected_images.front.display.en'),
                ]),
                source: $this->config['name'] ?? 'open_food_facts_family',
                metadata: [
                    'provider_label' => $this->config['label'] ?? null,
                    'countries' => data_get($product, 'countries'),
                    'labels' => data_get($product, 'labels'),
                    'packaging' => data_get($product, 'packaging'),
                    'quantity' => $quantity,
                    'categories_tags' => data_get($product, 'categories_tags', []),
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

    private function wasFound(array $payload): bool
    {
        $status = data_get($payload, 'status');

        return $status === 1 || $status === 'success' || data_get($payload, 'result.id') !== null || data_get($payload, 'product') !== null;
    }

    private function category(array $product): ?string
    {
        $categories = data_get($product, 'categories_tags');

        if (is_array($categories) && $categories !== []) {
            return ucfirst(str_replace(['en:', 'pt:', '-'], ['', '', ' '], (string) end($categories)));
        }

        return $this->firstTagValue(data_get($product, 'categories'));
    }

    private function firstTagValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter($value));
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim(explode(',', $value)[0]);
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function unitFromQuantity(?string $quantity): ?string
    {
        if (! $quantity) {
            return null;
        }

        if (preg_match('/\b(kg|g|mg|l|ml|un|und|unidade|unidades)\b/i', $quantity, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
