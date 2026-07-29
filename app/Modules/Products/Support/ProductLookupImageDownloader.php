<?php

namespace App\Modules\Products\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductLookupImageDownloader
{
    public function download(?string $url, string $gtin, string $source): ?string
    {
        if (! $url || ! $this->isAllowedUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('product_lookup.timeout_seconds', 4))
                ->connectTimeout((int) config('product_lookup.connect_timeout_seconds', 2))
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                ])
                ->withHeaders([
                    'User-Agent' => config('product_lookup.user_agent'),
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $extension = $this->extensionFromContentType($response->header('Content-Type'));

            if (! $extension) {
                return null;
            }

            $body = $this->limitedBody(
                $response,
                max(1, (int) config('product_lookup.max_image_bytes', 5 * 1024 * 1024))
            );

            if ($body === null || $body === '') {
                return null;
            }

            $filename = $gtin.'-'.Str::slug($source).'.'.$extension;
            $path = 'products/lookup/'.$filename;

            Storage::disk('public')->put($path, $body);

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    private function extensionFromContentType(?string $contentType): ?string
    {
        $contentType = strtolower(trim(explode(';', (string) $contentType)[0]));

        return match ($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
    }

    private function isAllowedUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || $host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || filter_var($host, FILTER_VALIDATE_IP)
        ) {
            return false;
        }

        return collect(config('product_lookup.image_allowed_hosts', []))
            ->map(fn ($allowed) => strtolower(ltrim(trim((string) $allowed), '.')))
            ->filter()
            ->contains(fn (string $allowed) => $host === $allowed || str_ends_with($host, '.'.$allowed));
    }

    private function limitedBody(Response $response, int $maxBytes): ?string
    {
        $contentLength = (int) ($response->header('Content-Length') ?? 0);

        if ($contentLength > $maxBytes) {
            return null;
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(min(8192, ($maxBytes + 1) - strlen($body)));

            if (strlen($body) > $maxBytes) {
                return null;
            }
        }

        return $body;
    }
}
