<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProviderCredential;
use LogicException;
use Throwable;

class EncryptedDatabaseFiscalCredentialResolver implements FiscalCredentialResolver
{
    public function isConfigured(): bool
    {
        try {
            return FiscalProviderCredential::query()
                ->whereNull('revoked_at')
                ->whereNotNull('token')
                ->exists();
        } catch (Throwable) {
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

        if (! $document->clinic_id || $reference === '') {
            throw $this->unavailable();
        }

        try {
            $credential = FiscalProviderCredential::query()
                ->where('clinic_id', $document->clinic_id)
                ->where('provider', $document->provider)
                ->where('environment', $document->environment)
                ->where('reference', $reference)
                ->whereNull('revoked_at')
                ->first();

            $token = $credential?->token;
        } catch (Throwable) {
            throw $this->unavailable();
        }

        if (! is_string($token)
            || strlen($token) < 16
            || strlen($token) > 512
            || trim($token) !== $token
            || preg_match('/[\x00-\x1F\x7F]/', $token)) {
            throw $this->unavailable();
        }

        return $token;
    }

    private function unavailable(): LogicException
    {
        return new LogicException('A credencial fiscal da clínica não está disponível para este documento.');
    }
}
