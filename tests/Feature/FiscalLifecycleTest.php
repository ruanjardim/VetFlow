<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Jobs\CancelFiscalDocumentJob;
use App\Modules\Fiscal\Jobs\ResendFiscalWebhookJob;
use App\Modules\Fiscal\Jobs\SubmitFiscalDocumentJob;
use App\Modules\Fiscal\Jobs\SyncFiscalDocumentStatusJob;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use App\Modules\Fiscal\Services\FiscalDocumentLifecycleService;
use App\Modules\Fiscal\Services\FiscalOperationsAlertService;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class FiscalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_queue_fails_closed_while_operational_switches_are_disabled(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user);

        $this->actingAs($user)
            ->from(route('fiscal.documents.show', $document->id))
            ->post(route('fiscal.documents.submit', $document->id))
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionHasErrors('fiscal');

        $this->assertSame('draft', $document->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_submission_is_queued_once_with_an_audited_transition(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user);
        $this->enableOperations(new FiscalLifecycleFakeProvider);

        $this->actingAs($user)
            ->post(route('fiscal.documents.submit', $document->id))
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($user)
            ->post(route('fiscal.documents.submit', $document->id))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('queued', $document->fresh()->status);
        $this->assertDatabaseHas('fiscal_document_events', [
            'fiscal_document_id' => $document->id,
            'event' => 'submission_queued',
            'from_status' => 'draft',
            'to_status' => 'queued',
            'actor_user_id' => $user->id,
        ]);
        Queue::assertPushedOn('fiscal', SubmitFiscalDocumentJob::class);
        Queue::assertPushed(SubmitFiscalDocumentJob::class, 1);
    }

    public function test_submission_and_reconciliation_apply_provider_states_idempotently(): void
    {
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $this->enableOperations($provider);
        $document = $this->document($clinic, $user, ['status' => 'queued']);
        $lifecycle = app(FiscalDocumentLifecycleService::class);

        $processing = $lifecycle->submit($document->id);

        $this->assertSame('processing', $processing?->status);
        $this->assertNotNull($processing?->submitted_at);
        $this->assertSame(1, $provider->submitCalls);
        $this->assertDatabaseHas('fiscal_document_events', [
            'fiscal_document_id' => $document->id,
            'event' => 'submission_started',
            'from_status' => 'queued',
            'to_status' => 'processing',
        ]);

        $authorized = $lifecycle->synchronize($document->id);
        $eventCount = $authorized?->events()->count();

        $this->assertSame('authorized', $authorized?->status);
        $this->assertSame('PROTO-100', $authorized?->protocol);
        $this->assertSame('CHAVE-100', $authorized?->access_key);
        $this->assertNotNull($authorized?->authorized_at);
        $this->assertSame(1, $provider->statusCalls);
        $unchanged = $lifecycle->synchronize($document->id);
        $this->assertSame('authorized', $unchanged?->status);
        $this->assertSame(2, $provider->statusCalls);
        $this->assertSame($eventCount, $document->events()->count());
    }

    public function test_nfce_can_use_the_homologation_queue_and_maps_its_authorization_fields(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $provider->statusResponse = [
            'status' => 'autorizado',
            'cnpj_emitente' => '00000000000911',
            'numero_protocolo' => 'PROTO-NFCE-100',
            'chave_nfe' => 'CHAVE-NFCE-100',
            'caminho_danfe' => '/arquivos/nfce-100.pdf',
        ];
        $this->enableOperations($provider);
        $document = $this->document($clinic, $user, [
            'type' => 'nfce',
            'issuer_snapshot' => [
                'corporate_name' => 'Clínica Operacional Niterói',
                'document' => '00000000000911',
                'address' => ['state' => 'RJ'],
            ],
            'request_payload' => [
                'focus_nfce' => [
                    'schema_version' => 'focus-nfce-rj-2026-10',
                    'payload' => ['numero' => '100', 'serie' => '0'],
                ],
            ],
            'series' => '0',
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.documents.submit', $document->id))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('queued', $document->fresh()->status);
        Queue::assertPushed(SubmitFiscalDocumentJob::class, 1);

        $processing = app(FiscalDocumentLifecycleService::class)->submit($document->id);
        $authorized = app(FiscalDocumentLifecycleService::class)->synchronize($document->id);

        $this->assertSame('processing', $processing?->status);
        $this->assertNotNull(data_get($processing?->request_payload, 'focus_nfce.payload.data_emissao'));
        $this->assertSame(
            data_get($processing?->request_payload, 'focus_nfce.payload.data_emissao'),
            data_get($processing?->request_payload, 'focus_nfce.transmission_prepared_at')
        );
        $this->assertSame('authorized', $authorized?->status);
        $this->assertSame('PROTO-NFCE-100', $authorized?->protocol);
        $this->assertSame('CHAVE-NFCE-100', $authorized?->access_key);
        $this->assertFalse(app(FiscalDocumentLifecycleService::class)->isWebhookRecoveryAvailable($authorized));
    }

    public function test_failed_nfce_submission_can_recover_by_manual_reference_consultation(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $provider->statusResponse = [
            'status' => 'autorizado',
            'cnpj_emitente' => '00000000000911',
            'numero_protocolo' => 'PROTO-NFCE-RECUPERADA',
            'chave_nfe' => 'CHAVE-NFCE-RECUPERADA',
        ];
        $this->enableOperations($provider);
        $document = $this->document($clinic, $user, [
            'type' => 'nfce',
            'status' => 'failed',
            'last_error_code' => 'provider_communication_failed',
            'last_error_message' => 'Falha anterior.',
            'issuer_snapshot' => [
                'document' => '00000000000911',
                'address' => ['state' => 'RJ'],
            ],
            'request_payload' => [
                'focus_nfce' => ['payload' => ['numero' => '100', 'serie' => '0']],
            ],
            'series' => '0',
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.documents.synchronize', $document->id))
            ->assertSessionDoesntHaveErrors();

        Queue::assertPushed(SyncFiscalDocumentStatusJob::class, 1);
        $recovered = app(FiscalDocumentLifecycleService::class)->synchronize($document->id);

        $this->assertSame('authorized', $recovered?->status);
        $this->assertSame('PROTO-NFCE-RECUPERADA', $recovered?->protocol);
        $this->assertSame('CHAVE-NFCE-RECUPERADA', $recovered?->access_key);
        $this->assertNull($recovered?->last_error_code);
    }

    public function test_authenticated_webhook_reconciles_once_and_rejects_another_issuer(): void
    {
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, ['status' => 'processing']);
        config()->set('fiscal.focus_nfe.webhook.enabled', true);
        config()->set('fiscal.focus_nfe.webhook.authorization_header', 'X-VetFlow-Focus-Webhook');
        config()->set('fiscal.focus_nfe.webhook.authorization_token', 'test-webhook-secret');
        $url = route('webhooks.focus-nfe.nfsen');

        $this->postJson($url, $this->authorizedWebhook($document))
            ->assertUnauthorized();

        $this->withHeader('X-VetFlow-Focus-Webhook', 'test-webhook-secret')
            ->postJson($url, array_replace($this->authorizedWebhook($document), ['cnpj_prestador' => '11111111111111']))
            ->assertUnprocessable();

        $this->withHeader('X-VetFlow-Focus-Webhook', 'test-webhook-secret')
            ->postJson($url, $this->authorizedWebhook($document))
            ->assertNoContent();

        $eventCount = $document->events()->count();

        $this->withHeader('X-VetFlow-Focus-Webhook', 'test-webhook-secret')
            ->postJson($url, $this->authorizedWebhook($document))
            ->assertNoContent();

        $document->refresh();
        $this->assertSame('authorized', $document->status);
        $this->assertSame('PROTO-WEBHOOK', $document->protocol);
        $this->assertSame($eventCount, $document->events()->count());
        $this->assertSame(1, $document->events()->where('event', 'webhook_received')->count());
        $this->assertStringNotContainsString(
            'test-webhook-secret',
            (string) DB::table('fiscal_document_events')->where('fiscal_document_id', $document->id)->value('metadata')
        );
    }

    public function test_webhook_is_not_discoverable_while_disabled(): void
    {
        $this->postJson(route('webhooks.focus-nfe.nfsen'), [
            'ref' => 'vf-unavailable',
            'status' => 'autorizado',
        ])->assertNotFound();
    }

    public function test_provider_identity_mismatch_is_rejected_without_mutating_the_document(): void
    {
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, ['status' => 'processing']);
        $lifecycle = app(FiscalDocumentLifecycleService::class);

        try {
            $lifecycle->applyProviderResponse($document->id, [
                'ref' => 'vf-another-document',
                'status' => 'autorizado',
            ], 'status');
            $this->fail('A resposta de outro documento deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fiscal', $exception->errors());
        }

        $this->assertSame('processing', $document->fresh()->status);
        $this->assertNull($document->fresh()->response_payload);
        $this->assertDatabaseCount('fiscal_document_events', 0);
    }

    public function test_stale_status_cannot_regress_an_authorization_and_webhook_can_recover_a_failed_job(): void
    {
        [$clinic, $user] = $this->context();
        $authorized = $this->document($clinic, $user, ['status' => 'authorized']);
        $failed = $this->document($clinic, $user, ['status' => 'failed']);
        $lifecycle = app(FiscalDocumentLifecycleService::class);

        $unchanged = $lifecycle->applyProviderResponse($authorized->id, [
            'ref' => $authorized->provider_reference,
            'status' => 'processando_autorizacao',
        ], 'webhook');

        $recovered = $lifecycle->applyProviderResponse($failed->id, [
            'ref' => $failed->provider_reference,
            'status' => 'autorizado',
            'protocolo' => 'PROTO-RECOVERED',
        ], 'webhook');

        $this->assertSame('authorized', $unchanged->status);
        $this->assertNull($unchanged->response_payload);
        $this->assertSame(0, $unchanged->events()->count());
        $this->assertSame('authorized', $recovered->status);
        $this->assertSame('PROTO-RECOVERED', $recovered->protocol);
        $this->assertSame(1, $recovered->events()->where('event', 'webhook_received')->count());
    }

    public function test_cancellation_fails_closed_while_operations_are_disabled(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, ['status' => 'authorized']);

        $this->actingAs($user)
            ->from(route('fiscal.documents.show', $document->id))
            ->post(route('fiscal.documents.cancel', $document->id), [
                'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
            ])
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionHasErrors('fiscal');

        $this->assertSame('authorized', $document->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_authorized_document_is_queued_for_cancellation_only_once(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, ['status' => 'authorized']);
        $this->enableOperations(new FiscalLifecycleFakeProvider);
        $reason = 'Serviço lançado no documento fiscal incorreto.';

        $this->actingAs($user)
            ->post(route('fiscal.documents.cancel', $document->id), ['cancellation_reason' => $reason])
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($user)
            ->post(route('fiscal.documents.cancel', $document->id), ['cancellation_reason' => $reason])
            ->assertSessionDoesntHaveErrors();

        $document->refresh();
        $this->assertSame('cancellation_queued', $document->status);
        $this->assertSame($reason, $document->cancellation_reason);
        $this->assertNotNull($document->cancellation_requested_at);
        $this->assertDatabaseHas('fiscal_document_events', [
            'fiscal_document_id' => $document->id,
            'event' => 'cancellation_queued',
            'from_status' => 'authorized',
            'to_status' => 'cancellation_queued',
            'actor_user_id' => $user->id,
        ]);
        Queue::assertPushedOn('fiscal', CancelFiscalDocumentJob::class);
        Queue::assertPushed(CancelFiscalDocumentJob::class, 1);
    }

    public function test_nfce_cancellation_is_blocked_after_the_operational_window(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $this->enableOperations(new FiscalLifecycleFakeProvider);
        config()->set('fiscal.nfce.cancellation_window_minutes', 30);
        $document = $this->document($clinic, $user, [
            'type' => 'nfce',
            'status' => 'authorized',
            'authorized_at' => now()->subMinutes(31),
            'issuer_snapshot' => [
                'document' => '00000000000911',
                'address' => ['state' => 'RJ'],
            ],
            'request_payload' => [
                'focus_nfce' => ['payload' => ['numero' => '100', 'serie' => '0']],
            ],
            'series' => '0',
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.documents.cancel', $document->id), [
                'cancellation_reason' => 'Venda registrada em duplicidade no caixa.',
            ])
            ->assertSessionHasErrors('fiscal');

        $this->assertSame('authorized', $document->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_provider_confirmation_finishes_cancellation_with_an_audit_event(): void
    {
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $this->enableOperations($provider);
        $reason = 'Serviço lançado no documento fiscal incorreto.';
        $document = $this->document($clinic, $user, [
            'status' => 'cancellation_queued',
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => now(),
            'response_payload' => [
                'status' => 'autorizado',
                'caminho_xml_nota_fiscal' => '/arquivos/nfse-test.xml',
                'caminho_danfe' => '/arquivos/nfse-test.pdf',
            ],
        ]);

        $cancelled = app(FiscalDocumentLifecycleService::class)->cancel($document->id);

        $this->assertSame('cancelled', $cancelled?->status);
        $this->assertNotNull($cancelled?->cancelled_at);
        $this->assertNull($cancelled?->last_error_code);
        $this->assertSame(1, $provider->cancelCalls);
        $this->assertSame($reason, $provider->lastCancellationReason);
        $this->assertSame('/arquivos/nfse-test.xml', data_get($cancelled?->response_payload, 'caminho_xml_nota_fiscal'));
        $this->assertSame(1, $cancelled?->events()->where('event', 'cancellation_succeeded')->count());
        $this->assertNull(app(FiscalDocumentLifecycleService::class)->cancel($document->id));
        $this->assertSame(1, $provider->cancelCalls);
    }

    public function test_authenticated_provider_update_can_confirm_a_queued_cancellation(): void
    {
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, [
            'status' => 'cancellation_queued',
            'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
            'cancellation_requested_at' => now(),
        ]);

        $cancelled = app(FiscalDocumentLifecycleService::class)->applyProviderResponse($document->id, [
            'status' => 'cancelado',
            'ref' => $document->provider_reference,
            'cnpj_prestador' => '00000000000911',
        ], 'webhook');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(1, $cancelled->events()->where('event', 'webhook_received')->count());
    }

    public function test_failed_cancellation_can_only_retry_with_the_frozen_reason(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $provider->cancelResponse = [
            'status' => 'erro_cancelamento',
            'status_sefaz' => 'E102',
            'mensagem_sefaz' => 'Cancelamento recusado pelo ambiente de homologação.',
        ];
        $this->enableOperations($provider);
        $reason = 'Serviço lançado no documento fiscal incorreto.';
        $document = $this->document($clinic, $user, [
            'status' => 'cancellation_queued',
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => now(),
        ]);

        $failed = app(FiscalDocumentLifecycleService::class)->cancel($document->id);

        $this->assertSame('cancellation_failed', $failed?->status);
        $this->assertSame('E102', $failed?->last_error_code);
        $this->assertSame(1, $failed?->events()->where('event', 'cancellation_failed')->count());

        $this->actingAs($user)
            ->post(route('fiscal.documents.cancel', $document->id), [
                'cancellation_reason' => 'Uma justificativa diferente e indevida.',
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame('cancellation_failed', $document->fresh()->status);

        $this->actingAs($user)
            ->post(route('fiscal.documents.cancel', $document->id), ['cancellation_reason' => $reason])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('cancellation_queued', $document->fresh()->status);
        Queue::assertPushed(CancelFiscalDocumentJob::class, 1);
    }

    public function test_exhausted_cancellation_does_not_expose_the_exception_message(): void
    {
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, [
            'status' => 'cancellation_queued',
            'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
            'cancellation_requested_at' => now(),
        ]);

        app(FiscalDocumentLifecycleService::class)->markCancellationFailed(
            $document->id,
            RuntimeException::class
        );

        $document->refresh();
        $this->assertSame('cancellation_failed', $document->status);
        $this->assertStringNotContainsString('segredo', (string) $document->last_error_message);
        $this->assertStringNotContainsString(
            'segredo',
            (string) DB::table('fiscal_document_events')->where('fiscal_document_id', $document->id)->value('metadata')
        );
    }

    public function test_stale_submission_failure_cannot_overwrite_a_cancellation_in_progress(): void
    {
        [$clinic, $user] = $this->context();
        $document = $this->document($clinic, $user, [
            'status' => 'cancellation_queued',
            'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
            'cancellation_requested_at' => now(),
        ]);

        app(FiscalDocumentLifecycleService::class)->markSubmissionFailed(
            $document->id,
            RuntimeException::class
        );

        $this->assertSame('cancellation_queued', $document->fresh()->status);
        $this->assertSame(0, $document->events()->where('event', 'submission_failed')->count());
    }

    public function test_operational_alerts_are_tenant_scoped_and_ignore_fresh_processing(): void
    {
        [$clinic, $user] = $this->context();
        $failed = $this->document($clinic, $user, ['status' => 'failed']);
        $staleProcessing = $this->document($clinic, $user, ['status' => 'processing']);
        $freshProcessing = $this->document($clinic, $user, ['status' => 'processing']);
        $staleCancellation = $this->document($clinic, $user, [
            'status' => 'cancellation_queued',
            'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
            'cancellation_requested_at' => now()->subHour(),
        ]);
        $failedCancellation = $this->document($clinic, $user, [
            'status' => 'cancellation_failed',
            'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
        ]);

        DB::table('fiscal_documents')->where('id', $staleProcessing->id)->update(['updated_at' => now()->subHours(4)]);
        DB::table('fiscal_documents')->where('id', $staleCancellation->id)->update(['updated_at' => now()->subHour()]);

        $otherClinic = Clinic::query()->create([
            'corporate_name' => 'Outra Clínica Fiscal',
            'trade_name' => 'Outra Clínica',
            'cnpj' => '00000000000830',
            'active' => true,
        ]);
        $otherUser = User::factory()->create(['clinic_id' => $otherClinic->id, 'active' => true]);
        $otherDocument = $this->document($otherClinic, $otherUser, ['status' => 'failed']);

        config()->set('fiscal.alerts.processing_stale_minutes', 180);
        config()->set('fiscal.alerts.cancellation_stale_minutes', 30);
        $alerts = app(FiscalOperationsAlertService::class)->forClinic($clinic->id);
        $ids = collect($alerts['items'])->pluck('document.id');

        $this->assertSame(4, $alerts['total']);
        $this->assertSame(2, $alerts['critical_count']);
        $this->assertTrue($ids->contains($failed->id));
        $this->assertTrue($ids->contains($staleProcessing->id));
        $this->assertTrue($ids->contains($staleCancellation->id));
        $this->assertTrue($ids->contains($failedCancellation->id));
        $this->assertFalse($ids->contains($freshProcessing->id));
        $this->assertFalse($ids->contains($otherDocument->id));
    }

    public function test_failed_cancellation_can_be_reconciled_without_regressing_to_authorized(): void
    {
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $provider->statusResponse = ['status' => 'autorizado'];
        $this->enableOperations($provider);
        $document = $this->document($clinic, $user, [
            'status' => 'cancellation_failed',
            'cancellation_reason' => 'Serviço lançado no documento fiscal incorreto.',
            'last_error_code' => 'provider_cancellation_failed',
            'last_error_message' => 'Falha anterior.',
        ]);

        $stillAuthorized = app(FiscalDocumentLifecycleService::class)->synchronize($document->id);

        $this->assertSame('cancellation_failed', $stillAuthorized?->status);
        $this->assertSame('cancellation_not_confirmed', $stillAuthorized?->last_error_code);
        $this->assertSame(1, $stillAuthorized?->events()->where('event', 'status_synchronized')->count());

        $provider->statusResponse = ['status' => 'cancelado'];
        $cancelled = app(FiscalDocumentLifecycleService::class)->synchronize($document->id);

        $this->assertSame('cancelled', $cancelled?->status);
        $this->assertNotNull($cancelled?->cancelled_at);
    }

    public function test_webhook_recovery_is_queued_with_a_cooldown_and_audited_after_provider_acceptance(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $this->enableOperations($provider);
        config()->set('fiscal.focus_nfe.webhook.enabled', true);
        config()->set('fiscal.focus_nfe.webhook.authorization_header', 'X-VetFlow-Focus-Webhook');
        config()->set('fiscal.focus_nfe.webhook.authorization_token', str_repeat('a', 32));
        config()->set('fiscal.recovery.webhook_resend_cooldown_minutes', 5);
        $document = $this->document($clinic, $user, ['status' => 'processing']);

        $this->actingAs($user)
            ->post(route('fiscal.documents.webhook-resend', $document->id))
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($user)
            ->post(route('fiscal.documents.webhook-resend', $document->id))
            ->assertSessionHasErrors('fiscal');

        $this->assertNotNull($document->fresh()->webhook_resend_requested_at);
        Queue::assertPushed(ResendFiscalWebhookJob::class, 1);
        $this->assertSame(1, $document->events()->where('event', 'webhook_resend_queued')->count());

        $recovered = app(FiscalDocumentLifecycleService::class)->resendWebhook($document->id);

        $this->assertSame(1, $provider->webhookResendCalls);
        $this->assertSame(1, $recovered?->events()->where('event', 'webhook_resend_requested')->count());
    }

    public function test_authorized_artifacts_are_proxied_without_exposing_the_provider_path(): void
    {
        [$clinic, $user] = $this->context();
        $provider = new FiscalLifecycleFakeProvider;
        $this->enableOperations($provider);
        $document = $this->document($clinic, $user, [
            'status' => 'authorized',
            'response_payload' => [
                'status' => 'autorizado',
                'caminho_xml_nota_fiscal' => '/private/provider-note.xml',
                'caminho_danfe' => '/private/provider-note.pdf',
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('fiscal.documents.artifact', [$document->id, 'xml']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<?xml version="1.0"?><NFSe/>', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('/private/provider-note.xml', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(1, $provider->artifactCalls);
        $this->assertSame(1, $document->events()->where('event', 'artifact_downloaded')->count());
    }

    /** @return array{Clinic, User} */
    private function context(): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Operacional Niterói',
            'trade_name' => 'Clínica Operacional',
            'cnpj' => '00000000000911',
            'active' => true,
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            ['name' => 'Fiscal', 'description' => 'Fiscal', 'group' => 'Fiscal', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal Operacional',
            'slug' => 'fiscal-operational-'.Str::lower(Str::random(8)),
            'description' => 'Teste',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach($permission->id);
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clinic, $user];
    }

    private function enableOperations(FiscalProvider $provider): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.submission_enabled', true);
        config()->set('fiscal.queue.connection', 'database');
        config()->set('fiscal.queue.name', 'fiscal');
        $this->app->instance(FiscalProvider::class, $provider);
        $this->app->instance(FiscalCredentialResolver::class, new FiscalLifecycleFakeCredentialResolver);
    }

    /** @param array<string, mixed> $overrides */
    private function document(Clinic $clinic, User $user, array $overrides = []): FiscalDocument
    {
        return FiscalDocument::query()->create(array_replace([
            'clinic_id' => $clinic->id,
            'created_by_user_id' => $user->id,
            'type' => 'nfse',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'status' => 'draft',
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 2,
            'issuer_snapshot' => [
                'corporate_name' => 'Clínica Operacional Niterói',
                'document' => '00000000000911',
                'municipality_code' => '3303302',
            ],
            'recipient_snapshot' => ['name' => 'Responsável Teste'],
            'items_snapshot' => [],
            'payments_snapshot' => [],
            'totals_snapshot' => ['total' => '100.00'],
            'request_payload' => [
                'focus_nfe' => [
                    'schema_version' => 'focus-nfsen-2026-10',
                    'payload' => ['numero_dps' => 100],
                ],
            ],
            'provider_reference' => 'vf-'.Str::ulid(),
            'series' => '1',
            'document_number' => '100',
        ], $overrides));
    }

    /** @return array<string, string> */
    private function authorizedWebhook(FiscalDocument $document): array
    {
        return [
            'ref' => $document->provider_reference,
            'status' => 'autorizado',
            'cnpj_prestador' => '00000000000911',
            'protocolo' => 'PROTO-WEBHOOK',
            'chave_nfse' => 'CHAVE-WEBHOOK',
            'numero' => '9001',
            'codigo_verificacao' => 'ABC123',
        ];
    }
}

class FiscalLifecycleFakeProvider implements FiscalProvider
{
    public int $submitCalls = 0;

    public int $statusCalls = 0;

    public int $cancelCalls = 0;

    public int $webhookResendCalls = 0;

    public int $artifactCalls = 0;

    public int $inutilizationCalls = 0;

    public ?string $lastCancellationReason = null;

    /** @var array<string, mixed> */
    public array $cancelResponse = ['status' => 'cancelado'];

    /** @var array<string, mixed> */
    public array $statusResponse = [
        'status' => 'autorizado',
        'protocolo' => 'PROTO-100',
        'chave_nfse' => 'CHAVE-100',
    ];

    public function submit(FiscalDocument $document): array
    {
        $this->submitCalls++;

        return [
            'status' => 'processando_autorizacao',
            'ref' => $document->provider_reference,
        ];
    }

    public function status(FiscalDocument $document): array
    {
        $this->statusCalls++;

        return array_replace([
            'ref' => $document->provider_reference,
        ], $this->statusResponse);
    }

    public function cancel(FiscalDocument $document, string $reason): array
    {
        $this->cancelCalls++;
        $this->lastCancellationReason = $reason;

        return array_replace([
            'ref' => $document->provider_reference,
        ], $this->cancelResponse);
    }

    public function inutilizeNfce(FiscalNfceInutilization $inutilization): array
    {
        $this->inutilizationCalls++;

        return [
            'status' => 'autorizado',
            'cnpj' => $inutilization->issuer_document,
            'modelo' => '65',
            'serie' => (string) $inutilization->series,
            'numero_inicial' => (string) $inutilization->number_start,
            'numero_final' => (string) $inutilization->number_end,
            'protocolo_sefaz' => 'PROTO-INUTILIZACAO',
        ];
    }

    public function resendWebhook(FiscalDocument $document): void
    {
        $this->webhookResendCalls++;
    }

    public function downloadArtifact(FiscalDocument $document, string $artifact): array
    {
        $this->artifactCalls++;

        return $artifact === 'xml'
            ? [
                'content' => '<?xml version="1.0"?><NFSe/>',
                'content_type' => 'application/xml; charset=UTF-8',
                'extension' => 'xml',
            ]
            : [
                'content' => '%PDF-1.7 test',
                'content_type' => 'application/pdf',
                'extension' => 'pdf',
            ];
    }
}

class FiscalLifecycleFakeCredentialResolver implements FiscalCredentialResolver
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
        return 'fake-lifecycle-token';
    }
}
