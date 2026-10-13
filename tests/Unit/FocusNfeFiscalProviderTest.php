<?php

namespace Tests\Unit;

use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use App\Modules\Fiscal\Services\FocusNfeFiscalProvider;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class FocusNfeFiscalProviderTest extends TestCase
{
    public function test_external_http_is_blocked_by_default(): void
    {
        config()->set('fiscal.external_http_enabled', false);
        Http::preventStrayRequests();

        try {
            $this->provider()->submit($this->document());
            $this->fail('O adaptador deveria bloquear tráfego fiscal externo.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'O tráfego HTTP fiscal externo está bloqueado pela configuração do VetFlow.',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_homologation_adapter_uses_basic_auth_and_sanitizes_the_lifecycle_responses(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.focus_nfe.homologation_url', 'https://homologacao.focusnfe.test');
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push([
                'status' => 'processando_autorizacao',
                'ref' => 'vf-test-ulid',
                'protocolo' => 'PROTOCOLO-1',
                'credencial_recebida' => 'não deve ser persistida',
            ], 202)
            ->push([
                'status' => 'autorizado',
                'numero' => '123',
                'chave_nfse' => 'CHAVE-1',
                'resposta_bruta' => ['privada' => true],
            ])
            ->push([
                'status' => 'cancelado',
                'numero' => '123',
                'segredo' => 'não deve ser persistido',
            ]);

        $provider = $this->provider();
        $document = $this->document();

        $submitted = $provider->submit($document);
        $status = $provider->status($document);
        $cancelled = $provider->cancel($document, 'Cancelamento solicitado em homologação');

        $this->assertSame([
            'status' => 'processando_autorizacao',
            'ref' => 'vf-test-ulid',
            'protocolo' => 'PROTOCOLO-1',
        ], $submitted);
        $this->assertSame([
            'status' => 'autorizado',
            'numero' => '123',
            'chave_nfse' => 'CHAVE-1',
        ], $status);
        $this->assertSame(['status' => 'cancelado', 'numero' => '123'], $cancelled);

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://homologacao.focusnfe.test/v2/nfsen?ref=vf-test-ulid'
                && ($request->header('Authorization')[0] ?? null) === 'Basic '.base64_encode('homologation-token:')
                && $request->data() === ['prestador' => ['cnpj' => '00000000000911']];
        });
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfsen/vf-test-ulid');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfsen/vf-test-ulid'
            && $request->data() === ['justificativa' => 'Cancelamento solicitado em homologação']);
    }

    public function test_cancellation_rejects_an_invalid_justification_before_http(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        Http::preventStrayRequests();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A justificativa do cancelamento deve ter entre 15 e 255 caracteres.');

        try {
            $this->provider()->cancel($this->document(), 'Muito curta');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_nfce_uses_its_reviewed_endpoints_and_refreshes_only_the_emission_time(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.focus_nfe.homologation_url', 'https://homologacao.focusnfe.test');
        $this->travelTo(Carbon::parse('2026-10-09 16:20:00', 'America/Sao_Paulo'));
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push([
                'status' => 'autorizado',
                'ref' => 'vf-nfce-test-ulid',
                'cnpj_emitente' => '00000000000911',
                'chave_nfe' => 'CHAVE-NFCE-1',
                'numero_protocolo' => 'PROTO-NFCE-1',
                'qrcode_url' => 'https://consulta.example/qrcode',
                'campo_privado' => 'não persistir',
            ], 201)
            ->push([
                'status' => 'autorizado',
                'ref' => 'vf-nfce-test-ulid',
                'serie' => '0',
                'numero' => '123',
            ])
            ->push([
                'status' => 'cancelado',
                'numero_protocolo' => 'PROTO-CANCELAMENTO-1',
                'caminho_xml_cancelamento' => '/arquivos/nfce-cancelamento.xml',
            ]);

        $document = $this->nfceDocument();
        $provider = $this->provider();

        $submitted = $provider->submit($document);
        $status = $provider->status($document);
        $cancelled = $provider->cancel($document, 'Venda registrada em duplicidade no caixa.');

        $this->assertSame('CHAVE-NFCE-1', $submitted['chave_nfe']);
        $this->assertSame('PROTO-NFCE-1', $submitted['numero_protocolo']);
        $this->assertArrayNotHasKey('campo_privado', $submitted);
        $this->assertSame('123', $status['numero']);
        $this->assertSame('PROTO-CANCELAMENTO-1', $cancelled['numero_protocolo']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://homologacao.focusnfe.test/v2/nfce?ref=vf-nfce-test-ulid'
                && ($payload['numero'] ?? null) === '123'
                && ($payload['data_emissao'] ?? null) === now('America/Sao_Paulo')->toIso8601String()
                && ($payload['natureza_operacao'] ?? null) === 'VENDA AO CONSUMIDOR';
        });
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfce/vf-nfce-test-ulid');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfce/vf-nfce-test-ulid');
    }

    public function test_nfce_reference_conflict_is_reconciled_before_the_submission_fails(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.focus_nfe.homologation_url', 'https://homologacao.focusnfe.test');
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['codigo' => 'referencia_em_uso'], 422)
            ->push([
                'status' => 'autorizado',
                'ref' => 'vf-nfce-test-ulid',
                'chave_nfe' => 'CHAVE-RECUPERADA',
            ]);

        $response = $this->provider()->submit($this->nfceDocument());

        $this->assertSame('autorizado', $response['status']);
        $this->assertSame('CHAVE-RECUPERADA', $response['chave_nfe']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfce/vf-nfce-test-ulid');
    }

    public function test_webhook_recovery_and_protected_artifacts_use_only_reviewed_urls(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.focus_nfe.homologation_url', 'https://homologacao.focusnfe.test');
        config()->set('fiscal.recovery.artifact_redirect_hosts', ['files.focusnfe.test']);
        config()->set('fiscal.recovery.artifact_max_bytes', 4096);
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push([['id' => 'hook-1']], 200)
            ->push('<?xml version="1.0"?><NFSe/>', 200, ['Content-Type' => 'application/xml'])
            ->push('', 302, ['Location' => 'https://files.focusnfe.test/nfse-test.pdf'])
            ->push('%PDF-1.7 test', 200, ['Content-Type' => 'application/pdf']);

        $document = $this->document();
        $document->response_payload = [
            'caminho_xml_nota_fiscal' => '/arquivos/nfse-test.xml',
            'caminho_danfe' => '/arquivos/nfse-test.pdf',
        ];
        $provider = $this->provider();

        $provider->resendWebhook($document);
        $xml = $provider->downloadArtifact($document, 'xml');
        $danfse = $provider->downloadArtifact($document, 'danfse');

        $this->assertSame('application/xml; charset=UTF-8', $xml['content_type']);
        $this->assertSame('xml', $xml['extension']);
        $this->assertSame('application/pdf', $danfse['content_type']);
        $this->assertSame('pdf', $danfse['extension']);
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfsen/vf-test-ulid/hook');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://homologacao.focusnfe.test/arquivos/nfse-test.xml'
            && ($request->header('Authorization')[0] ?? null) === 'Basic '.base64_encode('homologation-token:'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://files.focusnfe.test/nfse-test.pdf'
            && $request->header('Authorization') === []);
    }

    public function test_nfce_inutilization_uses_the_synchronous_homologation_endpoint_and_sanitizes_response(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.focus_nfe.homologation_url', 'https://homologacao.focusnfe.test');
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push([])
            ->push([
                'status' => 'autorizado',
                'status_sefaz' => '102',
                'cnpj' => '00000000000911',
                'modelo' => '65',
                'serie' => '0',
                'numero_inicial' => '10',
                'numero_final' => '12',
                'protocolo_sefaz' => 'PROTO-INUTILIZACAO-1',
                'segredo' => 'não persistir',
            ])
            ->push([[
                'status' => 'autorizado',
                'status_sefaz' => '102',
                'cnpj' => '00000000000911',
                'modelo' => '65',
                'serie' => '0',
                'numero_inicial' => '10',
                'numero_final' => '12',
                'protocolo_sefaz' => 'PROTO-INUTILIZACAO-1',
            ]]);
        $inutilization = new FiscalNfceInutilization([
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'issuer_document' => '00000000000911',
            'credential_reference' => 'vault://focus/niteroi',
            'series' => 0,
            'number_start' => 10,
            'number_end' => 12,
            'justification' => 'Faixa não utilizada após ajuste da sequência.',
            'request_payload' => [
                'cnpj' => '00000000000911',
                'serie' => '0',
                'numero_inicial' => '10',
                'numero_final' => '12',
                'justificativa' => 'Faixa não utilizada após ajuste da sequência.',
            ],
        ]);

        $response = $this->provider()->inutilizeNfce($inutilization);
        $reconciled = $this->provider()->inutilizeNfce($inutilization);

        $this->assertSame('autorizado', $response['status']);
        $this->assertSame('PROTO-INUTILIZACAO-1', $response['protocolo_sefaz']);
        $this->assertSame('PROTO-INUTILIZACAO-1', $reconciled['protocolo_sefaz']);
        $this->assertArrayNotHasKey('segredo', $response);
        Http::assertSentCount(3);
        $this->assertCount(1, collect(Http::recorded())->filter(
            fn (array $record): bool => $record[0]->method() === 'POST'
        ));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://homologacao.focusnfe.test/v2/nfce/inutilizacoes?'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://homologacao.focusnfe.test/v2/nfce/inutilizacao'
            && $request->data() === $inutilization->request_payload
            && ($request->header('Authorization')[0] ?? null) === 'Basic '.base64_encode('homologation-token:'));
    }

    public function test_artifact_download_rejects_an_absolute_provider_url_before_http(): void
    {
        config()->set('fiscal.external_http_enabled', true);
        Http::preventStrayRequests();
        $document = $this->document();
        $document->response_payload = [
            'caminho_xml_nota_fiscal' => 'https://untrusted.example/nfse.xml',
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('O caminho do arquivo fiscal retornado pelo provedor não é seguro.');

        try {
            $this->provider()->downloadArtifact($document, 'xml');
        } finally {
            Http::assertNothingSent();
        }
    }

    private function provider(): FocusNfeFiscalProvider
    {
        $credentials = new class implements FiscalCredentialResolver
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function canResolve(FiscalDocument $document): bool
            {
                return true;
            }

            public function tokenFor(FiscalDocument $document): string
            {
                return 'homologation-token';
            }
        };

        return new FocusNfeFiscalProvider($credentials);
    }

    private function document(): FiscalDocument
    {
        return new FiscalDocument([
            'ulid' => 'test-ulid',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'type' => 'nfse',
            'issuer_snapshot' => ['municipality_code' => '3303302'],
            'request_payload' => [
                'focus_nfe' => [
                    'payload' => ['prestador' => ['cnpj' => '00000000000911']],
                ],
            ],
        ]);
    }

    private function nfceDocument(): FiscalDocument
    {
        return new FiscalDocument([
            'ulid' => 'nfce-test-ulid',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'type' => 'nfce',
            'issuer_snapshot' => [
                'address' => ['state' => 'RJ'],
            ],
            'request_payload' => [
                'focus_nfce' => [
                    'payload' => [
                        'natureza_operacao' => 'VENDA AO CONSUMIDOR',
                        'numero' => '123',
                        'serie' => '0',
                        'data_emissao' => '2026-01-01T10:00:00-03:00',
                    ],
                ],
            ],
            'provider_reference' => 'vf-nfce-test-ulid',
        ]);
    }
}
