<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Models\FiscalDocument;
use JsonException;
use LogicException;

class ExternalFileFiscalCredentialResolver implements FiscalCredentialResolver
{
    public function isConfigured(): bool
    {
        try {
            $store = $this->store();

            return ($store['schema_version'] ?? null) === 1
                && is_array($store['credentials'] ?? null)
                && $store['credentials'] !== [];
        } catch (LogicException) {
            return false;
        }
    }

    public function canResolve(FiscalDocument $document): bool
    {
        try {
            $this->resolve($document);

            return true;
        } catch (LogicException) {
            return false;
        }
    }

    public function tokenFor(FiscalDocument $document): string
    {
        return $this->resolve($document);
    }

    private function resolve(FiscalDocument $document): string
    {
        $reference = trim((string) data_get($document->issuer_snapshot, 'provider_credential_reference'));

        if ($reference === ''
            || strlen($reference) > 160
            || ! preg_match('/^[A-Za-z0-9._:\/\-]+$/', $reference)) {
            throw $this->unavailable();
        }

        $store = $this->store();
        if (($store['schema_version'] ?? null) !== 1 || ! is_array($store['credentials'] ?? null)) {
            throw $this->unavailable();
        }

        $credential = $store['credentials'][$reference] ?? null;
        if (! is_array($credential)
            || ($credential['provider'] ?? null) !== $document->provider
            || ($credential['environment'] ?? null) !== $document->environment) {
            throw $this->unavailable();
        }

        $token = $credential['token'] ?? null;
        if (! is_string($token)
            || strlen($token) < 16
            || strlen($token) > 512
            || trim($token) !== $token
            || preg_match('/[\x00-\x1F\x7F]/', $token)) {
            throw $this->unavailable();
        }

        return $token;
    }

    /** @return array<string, mixed> */
    private function store(): array
    {
        $configuredPath = trim((string) config('fiscal.credentials.external_file'));
        $realPath = $configuredPath !== '' ? realpath($configuredPath) : false;

        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath) || $this->isInsideApplication($realPath)) {
            throw $this->unavailable();
        }

        $maximumBytes = min(1048576, max(1024, (int) config('fiscal.credentials.max_file_bytes', 65536)));
        $size = @filesize($realPath);
        if ($size === false || $size < 2 || $size > $maximumBytes) {
            throw $this->unavailable();
        }

        $contents = @file_get_contents($realPath);
        if (! is_string($contents) || strlen($contents) > $maximumBytes) {
            throw $this->unavailable();
        }

        try {
            $decoded = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->unavailable();
        }

        if (! is_array($decoded)) {
            throw $this->unavailable();
        }

        return $decoded;
    }

    private function isInsideApplication(string $realPath): bool
    {
        $applicationPath = realpath(base_path());
        if ($applicationPath === false) {
            return true;
        }

        $path = str_replace('\\', '/', $realPath);
        $base = rtrim(str_replace('\\', '/', $applicationPath), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $base = strtolower($base);
        }

        return $path === $base || str_starts_with($path, $base.'/');
    }

    private function unavailable(): LogicException
    {
        return new LogicException('A credencial fiscal externa não está disponível para este documento.');
    }
}
