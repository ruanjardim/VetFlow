<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalProfileService
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly FiscalDpsSequenceService $dpsSequences,
        private readonly FiscalNfceSequenceService $nfceSequences
    ) {}

    public function forClinic(int $clinicId): FiscalProfile
    {
        $clinic = Clinic::query()->findOrFail($clinicId);

        return FiscalProfile::query()->firstOrNew(
            ['clinic_id' => $clinicId],
            [
                'enabled' => false,
                'environment' => 'homologation',
                'nfce_enabled' => false,
                'nfse_enabled' => false,
                'municipality_code' => FiscalMunicipalityCatalog::inferFromClinic($clinic),
                'configuration_version' => 1,
            ]
        );
    }

    /** @param array<string, mixed> $data */
    public function update(int $clinicId, array $data): FiscalProfile
    {
        return DB::transaction(function () use ($clinicId, $data): FiscalProfile {
            $profile = $this->forClinic($clinicId);
            if ($profile->exists && $profile->environment === FiscalEnvironmentGuard::PRODUCTION) {
                throw ValidationException::withMessages([
                    'fiscal' => 'Desative formalmente a produção antes de alterar a configuração fiscal do estabelecimento.',
                ]);
            }
            $before = $profile->exists ? $this->snapshot($profile) : [];
            $sequenceData = $data;
            $data = $this->normalize($data, $profile);

            if ($profile->exists && $before !== $this->comparableSnapshot($data, $profile)) {
                $data['configuration_version'] = $profile->configuration_version + 1;
            }

            $profile->fill($data + [
                'clinic_id' => $clinicId,
                'configuration_version' => $profile->configuration_version ?: 1,
            ]);
            $profile->save();
            $this->dpsSequences->configure($profile, $sequenceData);
            $this->nfceSequences->configure($profile, $sequenceData);

            $this->audit->record(
                'fiscal.profile.updated',
                $profile,
                $before,
                $this->snapshot($profile),
                subjectLabel: 'Configuração fiscal',
                clinicId: $clinicId
            );

            return $profile;
        });
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data, FiscalProfile $profile): array
    {
        $metadata = is_array($profile->metadata) ? $profile->metadata : [];
        if (config('fiscal.credentials.driver') !== 'encrypted_database') {
            $metadata['provider_credential_reference'] = $this->blankToNull($data['provider_credential_reference'] ?? null);
        }
        $metadata['focus_nfe'] = [
            'dps_series' => $this->blankToNull($data['nfse_dps_series'] ?? null),
            'simple_national_option' => $this->blankToNull($data['simple_national_option'] ?? null),
            'simple_national_tax_regime' => $this->blankToNull($data['simple_national_tax_regime'] ?? null),
            'special_tax_regime' => $this->blankToNull($data['special_tax_regime'] ?? null),
        ];
        $metadata['focus_nfce'] = [
            'series' => $this->blankToNull($data['nfce_series'] ?? null),
            'csc_id' => $this->digitsOrNull($data['nfce_csc_id'] ?? null),
            'csc_reference' => $this->blankToNull($data['nfce_csc_reference'] ?? null),
            'pix_payment_code' => $this->blankToNull($data['nfce_pix_payment_code'] ?? null),
            'sefaz_credentialed' => (bool) ($data['nfce_sefaz_credentialed'] ?? false),
        ];

        return [
            'enabled' => (bool) $data['enabled'],
            'environment' => $profile->exists ? $profile->environment : FiscalEnvironmentGuard::HOMOLOGATION,
            'provider' => $this->blankToNull($data['provider'] ?? null),
            'nfce_enabled' => (bool) $data['nfce_enabled'],
            'nfse_enabled' => (bool) $data['nfse_enabled'],
            'state_registration' => $this->blankToNull($data['state_registration'] ?? null),
            'municipal_registration' => $this->blankToNull($data['municipal_registration'] ?? null),
            'tax_regime' => $this->blankToNull($data['tax_regime'] ?? null),
            'municipality_code' => $this->digitsOrNull($data['municipality_code'] ?? null),
            'cnae' => $this->digitsOrNull($data['cnae'] ?? null),
            'certificate_reference' => $this->blankToNull($data['certificate_reference'] ?? null),
            'certificate_fingerprint' => $this->blankToNull($data['certificate_fingerprint'] ?? null),
            'certificate_expires_at' => $this->blankToNull($data['certificate_expires_at'] ?? null),
            'metadata' => $metadata,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(FiscalProfile $profile): array
    {
        $snapshot = $profile->only([
            'enabled', 'environment', 'provider', 'nfce_enabled', 'nfse_enabled',
            'state_registration', 'municipal_registration', 'tax_regime',
            'municipality_code', 'cnae', 'certificate_reference',
            'certificate_fingerprint', 'certificate_expires_at', 'configuration_version',
            'metadata',
        ]);

        $snapshot['certificate_expires_at'] = $profile->certificate_expires_at?->toDateString();

        return $snapshot;
    }

    /** @param array<string, mixed> $data */
    private function comparableSnapshot(array $data, FiscalProfile $profile): array
    {
        return array_replace($this->snapshot($profile), $data, [
            'configuration_version' => $profile->configuration_version,
        ]);
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
