<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalNfceSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;

class FiscalNfceReadinessService
{
    /** @return array<string, mixed> */
    public function evaluate(Clinic $clinic, FiscalProfile $profile, ?FiscalNfceSequence $sequence): array
    {
        $certificateIsCurrent = $profile->certificate_expires_at?->isFuture() ?? false;
        $checks = [
            $this->check('state', 'Estabelecimento emitente no RJ', strtoupper((string) $clinic->state) === 'RJ'),
            $this->check('provider', 'Focus NFe selecionada', $profile->provider === FiscalProviderCatalog::FOCUS_NFE),
            $this->check('cnpj', 'CNPJ do emitente com 14 dígitos', strlen($this->digits($clinic->cnpj)) === 14),
            $this->check('state_registration', 'Inscrição estadual informada', filled($profile->state_registration)),
            $this->check('tax_regime', 'Regime tributário informado', in_array($profile->tax_regime, ['simple_national', 'simple_excess_sublimit', 'normal'], true)),
            $this->check('sefaz_credential', 'Credenciamento NFC-e confirmado na SEFAZ-RJ', (bool) data_get($profile->metadata, 'focus_nfce.sefaz_credentialed', false)),
            $this->check('csc_id', 'Identificador do CSC de homologação com 6 dígitos', strlen($this->digits(data_get($profile->metadata, 'focus_nfce.csc_id'))) === 6),
            $this->check('csc_reference', 'Referência externa do CSC de homologação informada', filled(data_get($profile->metadata, 'focus_nfce.csc_reference'))),
            $this->check('certificate_reference', 'Referência externa do certificado informada', filled($profile->certificate_reference)),
            $this->check('certificate_expiry', 'Certificado informado e dentro da validade', $certificateIsCurrent),
            $this->check('sequence', 'Série e próximo número da NFC-e configurados', $sequence !== null && $sequence->next_number >= 1 && $sequence->next_number <= FiscalNfceSequenceService::MAX_NUMBER),
        ];
        $readyCount = collect($checks)->where('ready', true)->count();

        return [
            'checks' => $checks,
            'ready_count' => $readyCount,
            'total_count' => count($checks),
            'configuration_ready' => $readyCount === count($checks),
            'scope' => 'RJ · modelo 65 · homologação',
        ];
    }

    /** @return array{key: string, label: string, ready: bool} */
    private function check(string $key, string $label, bool $ready): array
    {
        return compact('key', 'label', 'ready');
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
