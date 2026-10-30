<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProductionActivation;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;

class FiscalEnvironmentGuard
{
    public const HOMOLOGATION = 'homologation';

    public const PRODUCTION = 'production';

    public function productionRuntimeEnabled(): bool
    {
        return (bool) config('fiscal.production.enabled', false);
    }

    public function activeProductionForClinic(int $clinicId): ?FiscalProductionActivation
    {
        return FiscalProductionActivation::withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->whereNull('revoked_at')
            ->latest('activated_at')
            ->latest('id')
            ->first();
    }

    public function allowsProfile(FiscalProfile $profile): bool
    {
        if ($profile->environment === self::HOMOLOGATION) {
            return true;
        }

        if ($profile->environment !== self::PRODUCTION || ! $this->productionRuntimeEnabled()) {
            return false;
        }

        $activation = $this->activeProductionForClinic((int) $profile->clinic_id);

        return $activation !== null
            && hash_equals((string) $activation->ulid, (string) data_get($profile->metadata, 'production.activation_ulid'))
            && (int) $activation->profile_version === (int) $profile->configuration_version;
    }

    public function allowsDocument(FiscalDocument $document): bool
    {
        if (! $this->supportsScope($document)) {
            return false;
        }

        if ($document->environment === self::HOMOLOGATION) {
            return true;
        }

        if ($document->environment !== self::PRODUCTION || ! $this->productionRuntimeEnabled()) {
            return false;
        }

        $activationUlid = (string) data_get($document->issuer_snapshot, 'production_activation.ulid');
        if ($activationUlid === '') {
            return false;
        }

        $activation = FiscalProductionActivation::withoutGlobalScopes()
            ->where('clinic_id', $document->clinic_id)
            ->where('ulid', $activationUlid)
            ->whereNull('revoked_at')
            ->first();

        return $activation !== null
            && (int) $activation->profile_version === (int) $document->profile_version
            && hash_equals(
                (string) $activation->evidence_fingerprint,
                (string) data_get($document->issuer_snapshot, 'production_activation.evidence_fingerprint')
            );
    }

    public function supportsScope(FiscalDocument $document): bool
    {
        if ($document->provider !== FiscalProviderCatalog::FOCUS_NFE
            || ! in_array($document->environment, [self::HOMOLOGATION, self::PRODUCTION], true)) {
            return false;
        }

        return ($document->type === FiscalDocumentType::NFSE
                && data_get($document->issuer_snapshot, 'municipality_code') === FiscalMunicipalityCatalog::NITEROI_IBGE_CODE)
            || ($document->type === FiscalDocumentType::NFCE
                && strtoupper((string) data_get($document->issuer_snapshot, 'address.state')) === 'RJ');
    }
}
