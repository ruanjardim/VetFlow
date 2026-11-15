<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;

class FiscalMunicipalityReadinessService
{
    public function __construct(private readonly FiscalDpsSequenceService $dpsSequences) {}

    /** @return array<string, mixed> */
    public function evaluate(Clinic $clinic, FiscalProfile $profile): array
    {
        $municipality = FiscalMunicipalityCatalog::find($profile->municipality_code);
        $document = preg_replace('/\D+/', '', (string) $clinic->cnpj);
        $certificateIsCurrent = $profile->certificate_expires_at
            && $profile->certificate_expires_at->isAfter(now()->startOfDay());
        $dpsSequence = $this->dpsSequences->currentFor($profile);

        $checks = [
            $this->check('municipality', 'Município piloto identificado', $municipality !== null),
            $this->check('provider', 'Provedor fiscal selecionado', $profile->provider === FiscalProviderCatalog::FOCUS_NFE),
            $this->check('provider_credential_reference', 'Referência externa da credencial do provedor informada', filled(data_get($profile->metadata, 'provider_credential_reference'))),
            $this->check('dps_sequence', 'Série e próximo número da DPS configurados', $dpsSequence !== null),
            $this->check('simple_national_option', 'Situação perante o Simples Nacional informada', filled(data_get($profile->metadata, 'focus_nfe.simple_national_option'))),
            $this->check('special_tax_regime', 'Regime especial de tributação municipal informado', data_get($profile->metadata, 'focus_nfe.special_tax_regime') !== null),
            $this->check('document', 'CNPJ do estabelecimento com 14 dígitos', strlen($document) === 14),
            $this->check('municipal_registration', 'Inscrição municipal informada', filled($profile->municipal_registration)),
            $this->check('tax_regime', 'Regime tributário informado', filled($profile->tax_regime)),
            $this->check('cnae', 'CNAE principal informado', filled($profile->cnae)),
            $this->check('certificate_reference', 'Referência externa do certificado informada', filled($profile->certificate_reference)),
            $this->check('certificate_expiry', 'Certificado dentro da validade', (bool) $certificateIsCurrent),
        ];

        return [
            'municipality' => $municipality,
            'checks' => $checks,
            'ready_count' => collect($checks)->where('ready', true)->count(),
            'total_count' => count($checks),
            'configuration_ready' => collect($checks)->every(fn (array $check): bool => $check['ready']),
            'submission_enabled' => (bool) config('fiscal.submission_enabled', false),
        ];
    }

    /** @return array{key: string, label: string, ready: bool} */
    private function check(string $key, string $label, bool $ready): array
    {
        return compact('key', 'label', 'ready');
    }
}
