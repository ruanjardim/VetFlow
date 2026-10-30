<?php

namespace App\Support\Operations;

use Illuminate\Support\Env;

final class ReleaseRouteCachePath
{
    public static function configure(string $basePath): ?string
    {
        $path = self::resolve($basePath);

        if ($path === null) {
            return self::configuredPath();
        }

        // A valid release marker is authoritative. In particular, replace a
        // legacy APP_ROUTES_CACHE value left in the hosting environment.
        putenv('APP_ROUTES_CACHE='.$path);
        $_ENV['APP_ROUTES_CACHE'] = $path;
        $_SERVER['APP_ROUTES_CACHE'] = $path;
        Env::enablePutenv();

        return $path;
    }

    public static function resolve(string $basePath): ?string
    {
        $marker = rtrim($basePath, '/\\').'/bootstrap/cache/vetflow-release.sha';

        if (! is_file($marker)) {
            return null;
        }

        $sha = strtolower(trim((string) file_get_contents($marker)));

        if (preg_match('/^[a-f0-9]{40}$/', $sha) !== 1) {
            return null;
        }

        return 'bootstrap/cache/routes-v7-'.$sha.'.php';
    }

    private static function configuredPath(): ?string
    {
        foreach ([getenv('APP_ROUTES_CACHE'), $_ENV['APP_ROUTES_CACHE'] ?? null, $_SERVER['APP_ROUTES_CACHE'] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
