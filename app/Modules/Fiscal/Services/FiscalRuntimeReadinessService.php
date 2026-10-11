<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;

class FiscalRuntimeReadinessService
{
    public function __construct(
        private readonly FiscalProvider $provider,
        private readonly FiscalCredentialResolver $credentials,
    ) {}

    /** @return array{checks: array<int, array{key: string, label: string, ready: bool}>, ready_count: int, total_count: int, operational_ready: bool} */
    public function evaluate(FiscalProfile $profile): array
    {
        $reference = (string) data_get($profile->metadata, 'provider_credential_reference');
        $probe = new FiscalDocument([
            'clinic_id' => $profile->clinic_id,
            'provider' => $profile->provider,
            'environment' => 'homologation',
            'issuer_snapshot' => [
                'provider_credential_reference' => $reference,
            ],
        ]);
        $webhookHeader = (string) config('fiscal.focus_nfe.webhook.authorization_header', '');
        $webhookToken = (string) config('fiscal.focus_nfe.webhook.authorization_token', '');
        $queueConnection = (string) config('fiscal.queue.connection', 'sync');
        $queueName = trim((string) config('fiscal.queue.name', ''));
        $credentialDriver = (string) config('fiscal.credentials.driver');
        $credentialStoreLabel = $credentialDriver === 'encrypted_database'
            ? 'Conta Focus própria da clínica conectada com token criptografado'
            : 'Arquivo externo de credenciais válido e fora do projeto';

        $checks = [
            $this->check(
                'adapter',
                'Adaptador Focus habilitado explicitamente no servidor',
                config('fiscal.provider_adapter') === FiscalProviderCatalog::FOCUS_NFE
                    && $this->provider instanceof FocusNfeFiscalProvider
            ),
            $this->check(
                'credential_store',
                $credentialStoreLabel,
                in_array($credentialDriver, ['external_file', 'encrypted_database'], true)
                    && $this->credentials->isConfigured()
            ),
            $this->check(
                'credential_reference',
                'Referência da clínica resolvida para provedor e ambiente corretos',
                $profile->provider === FiscalProviderCatalog::FOCUS_NFE
                    && $reference !== ''
                    && $this->credentials->canResolve($probe)
            ),
            $this->check(
                'queue',
                'Fila assíncrona fiscal configurada',
                $queueConnection !== '' && $queueConnection !== 'sync' && $queueName !== ''
            ),
            $this->check(
                'webhook',
                'Webhook com autenticação forte habilitado',
                (bool) config('fiscal.focus_nfe.webhook.enabled', false)
                    && strlen($webhookToken) >= 32
                    && strlen($webhookToken) <= 512
                    && (bool) preg_match('/^[A-Za-z0-9-]+$/', $webhookHeader)
            ),
            $this->check(
                'external_http',
                'Tráfego fiscal de homologação liberado no servidor',
                (bool) config('fiscal.external_http_enabled', false)
            ),
            $this->check(
                'submission',
                'Fila de transmissão fiscal liberada no servidor',
                (bool) config('fiscal.submission_enabled', false)
            ),
        ];

        $readyCount = collect($checks)->where('ready', true)->count();

        return [
            'checks' => $checks,
            'ready_count' => $readyCount,
            'total_count' => count($checks),
            'operational_ready' => $readyCount === count($checks),
        ];
    }

    /** @return array{key: string, label: string, ready: bool} */
    private function check(string $key, string $label, bool $ready): array
    {
        return compact('key', 'label', 'ready');
    }
}
