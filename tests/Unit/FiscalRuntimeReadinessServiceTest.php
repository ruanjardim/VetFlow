<?php

namespace Tests\Unit;

use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Services\ExternalFileFiscalCredentialResolver;
use App\Modules\Fiscal\Services\FiscalRuntimeReadinessService;
use App\Modules\Fiscal\Services\FocusNfeFiscalProvider;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Tests\TestCase;

class FiscalRuntimeReadinessServiceTest extends TestCase
{
    private ?string $temporaryFile = null;

    protected function tearDown(): void
    {
        if ($this->temporaryFile && is_file($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }

        parent::tearDown();
    }

    public function test_it_reports_full_homologation_readiness_without_exposing_secret_material(): void
    {
        $reference = 'vault://vetflow/focus/niteroi';
        $token = 'fake-'.str_repeat('r', 32);
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'vetflow-fiscal-readiness-') ?: null;
        $this->assertNotNull($this->temporaryFile);
        file_put_contents($this->temporaryFile, json_encode([
            'schema_version' => 1,
            'credentials' => [
                $reference => [
                    'provider' => FiscalProviderCatalog::FOCUS_NFE,
                    'environment' => 'homologation',
                    'token' => $token,
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        config()->set('fiscal.provider_adapter', FiscalProviderCatalog::FOCUS_NFE);
        config()->set('fiscal.credentials.driver', 'external_file');
        config()->set('fiscal.credentials.external_file', $this->temporaryFile);
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.submission_enabled', true);
        config()->set('fiscal.queue.connection', 'database');
        config()->set('fiscal.queue.name', 'fiscal');
        config()->set('fiscal.focus_nfe.webhook.enabled', true);
        config()->set('fiscal.focus_nfe.webhook.authorization_header', 'X-VetFlow-Focus-Webhook');
        config()->set('fiscal.focus_nfe.webhook.authorization_token', str_repeat('w', 32));

        $resolver = new ExternalFileFiscalCredentialResolver;
        $service = new FiscalRuntimeReadinessService(
            new FocusNfeFiscalProvider($resolver),
            $resolver,
        );
        $profile = new FiscalProfile([
            'clinic_id' => 10,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'metadata' => ['provider_credential_reference' => $reference],
        ]);

        $result = $service->evaluate($profile);
        $serialized = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertTrue($result['operational_ready']);
        $this->assertSame($result['total_count'], $result['ready_count']);
        $this->assertStringNotContainsString($reference, $serialized);
        $this->assertStringNotContainsString($token, $serialized);
        $this->assertStringNotContainsString($this->temporaryFile, $serialized);
    }

    public function test_any_missing_gate_keeps_homologation_blocked(): void
    {
        $resolver = new ExternalFileFiscalCredentialResolver;
        $service = new FiscalRuntimeReadinessService(
            new FocusNfeFiscalProvider($resolver),
            $resolver,
        );
        $profile = new FiscalProfile([
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'metadata' => ['provider_credential_reference' => 'vault://vetflow/focus/niteroi'],
        ]);

        $result = $service->evaluate($profile);

        $this->assertFalse($result['operational_ready']);
        $this->assertContains(false, collect($result['checks'])->pluck('ready')->all());
    }
}
