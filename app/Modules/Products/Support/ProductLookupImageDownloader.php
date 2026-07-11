<?php

namespace App\Modules\Products\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductLookupImageDownloader
{
    public function download(?string $url, string $gtin, string $source): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('product_lookup.timeout_seconds', 4))
                ->withHeaders([
                    'User-Agent' => config('product_lookup.user_agent'),
                ])
                ->get($url);

            if (! $response->successful() || $response->body() === '') {
                return null;
            }

            $extension = $this->extensionFromContentType($response->header('Content-Type')) ?: 'jpg';
            $filename = $gtin.'-'.Str::slug($source).'.'.$extension;
            $path = 'products/lookup/'.$filename;

            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    private function extensionFromContentType(?string $contentType): ?string
    {
        return match (strtolower((string) $contentType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
    }
}
