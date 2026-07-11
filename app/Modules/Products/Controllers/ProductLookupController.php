<?php

namespace App\Modules\Products\Controllers;

use App\Modules\Products\Services\ProductLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductLookupController
{
    public function show(string $gtin, ProductLookupService $lookupService): JsonResponse
    {
        $result = $lookupService->lookup($gtin);

        if (! $result) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'message' => 'Produto nao encontrado nas bases. Preencha os dados e salve para o VetFlow aprender este EAN.',
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
            'found' => true,
            'manual_allowed' => true,
            'source' => $result->source,
            'global_product_id' => $result->metadata['global_product_id'] ?? null,
            'status' => $result->metadata['status'] ?? null,
            'source_confidence' => $result->metadata['source_confidence'] ?? null,
            'product' => $product,
        ]);
    }

    public function image(string $filename): StreamedResponse
    {
        $filename = basename($filename);
        $path = collect([
            'products/lookup/'.$filename,
            'products/manual/'.$filename,
        ])->first(fn (string $path) => Storage::disk('public')->exists($path));

        abort_unless($path, 404);

        return Storage::disk('public')->response($path);
    }
}
