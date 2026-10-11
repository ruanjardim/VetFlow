<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Jobs\CancelFiscalDocumentJob;
use App\Modules\Fiscal\Jobs\ResendFiscalWebhookJob;
use App\Modules\Fiscal\Jobs\SubmitFiscalDocumentJob;
use App\Modules\Fiscal\Jobs\SyncFiscalDocumentStatusJob;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDocumentEvent;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FiscalDocumentLifecycleService
{
    private const RECONCILABLE_STATUSES = [
        'processing', 'authorized', 'cancellation_queued', 'cancellation_failed', 'cancelled', 'failed',
    ];

    private const WEBHOOK_RECOVERY_STATUSES = [
        'processing', 'authorized', 'cancellation_queued', 'cancellation_failed', 'cancelled',
    ];

    private const RESPONSE_FIELDS = [
        'status', 'ref', 'protocolo', 'cnpj_prestador', 'chave_nfse',
        'numero', 'codigo_verificacao', 'status_sefaz', 'mensagem_sefaz',
        'caminho_xml_nota_fiscal', 'caminho_danfe', 'cnpj_emitente',
        'chave_nfe', 'serie', 'qrcode_url', 'url_consulta_nf',
        'caminho_xml_cancelamento', 'numero_protocolo',
    ];

    public function __construct(
        private readonly FiscalProvider $provider,
        private readonly FiscalCredentialResolver $credentials,
    ) {}

    public function isAvailable(?FiscalDocument $document = null): bool
    {
        $available = (bool) config('fiscal.external_http_enabled', false)
            && (bool) config('fiscal.submission_enabled', false)
            && (string) config('fiscal.queue.connection', 'sync') !== 'sync'
            && trim((string) config('fiscal.queue.name', '')) !== ''
            && ! $this->provider instanceof DisabledFiscalProvider
            && $this->credentials->isConfigured();

        return $available && ($document === null || ($this->isSupported($document) && $this->credentials->canResolve($document)));
    }

    public function isWebhookRecoveryAvailable(?FiscalDocument $document = null): bool
    {
        if ($document?->type === FiscalDocumentType::NFCE) {
            return false;
        }

        $token = (string) config('fiscal.focus_nfe.webhook.authorization_token', '');
        $header = (string) config('fiscal.focus_nfe.webhook.authorization_header', '');

        return $this->isAvailable($document)
            && (bool) config('fiscal.focus_nfe.webhook.enabled', false)
            && strlen($token) >= 32
            && strlen($token) <= 512
            && (bool) preg_match('/^[A-Za-z0-9-]+$/', $header);
    }

    public function queueForSubmission(FiscalDocument $document, User $actor): FiscalDocument
    {
        $document = DB::transaction(function () use ($document, $actor): FiscalDocument {
            $locked = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);

            $this->assertAvailable($locked);
            $this->assertSupported($locked);

            if ($locked->status === 'queued') {
                return $locked;
            }

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'fiscal' => 'Somente um documento em rascunho pode entrar na fila de transmissão.',
                ]);
            }

            if (! is_array(data_get($locked->request_payload, $this->payloadRoot($locked).'.payload'))) {
                throw ValidationException::withMessages([
                    'fiscal' => 'Gere e valide o payload Focus NFe antes de solicitar a transmissão.',
                ]);
            }

            $locked->update(['status' => 'queued']);
            $this->event($locked, 'submission_queued', 'draft', 'queued', $actor, [
                'provider' => $locked->provider,
                'environment' => $locked->environment,
            ]);

            DB::afterCommit(fn () => $this->dispatchSubmission($locked->id));

            return $locked->fresh(['events']);
        });

        return $document;
    }

    public function queueSynchronization(FiscalDocument $document, User $actor): FiscalDocument
    {
        return DB::transaction(function () use ($document, $actor): FiscalDocument {
            $locked = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);

            $this->assertAvailable($locked);
            $this->assertSupported($locked);

            if (! in_array($locked->status, self::RECONCILABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'fiscal' => 'A consulta manual está disponível após o início do processamento ou uma falha de comunicação.',
                ]);
            }

            $this->event($locked, 'status_sync_queued', $locked->status, $locked->status, $actor, [
                'provider' => $locked->provider,
                'source' => 'manual',
            ]);

            DB::afterCommit(fn () => $this->dispatchSynchronization($locked->id));

            return $locked->fresh(['events']);
        });
    }

    public function queueWebhookResend(FiscalDocument $document, User $actor): FiscalDocument
    {
        return DB::transaction(function () use ($document, $actor): FiscalDocument {
            $locked = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);

            $this->assertAvailable($locked);
            $this->assertSupported($locked);
            $this->assertWebhookRecoveryAvailable();

            if (! in_array($locked->status, self::WEBHOOK_RECOVERY_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'fiscal' => 'O reenvio do webhook está disponível somente para documentos já recebidos pelo provedor.',
                ]);
            }

            $cooldownMinutes = max(1, (int) config('fiscal.recovery.webhook_resend_cooldown_minutes', 5));
            if ($locked->webhook_resend_requested_at?->isAfter(now()->subMinutes($cooldownMinutes))) {
                throw ValidationException::withMessages([
                    'fiscal' => "Aguarde {$cooldownMinutes} minuto(s) antes de solicitar outro reenvio do webhook.",
                ]);
            }

            $status = $locked->status;
            $locked->update(['webhook_resend_requested_at' => now()]);
            $this->event($locked, 'webhook_resend_queued', $status, $status, $actor, [
                'provider' => $locked->provider,
                'cooldown_minutes' => $cooldownMinutes,
            ]);

            DB::afterCommit(fn () => $this->dispatchWebhookResend($locked->id));

            return $locked->fresh(['events']);
        });
    }

    public function queueCancellation(FiscalDocument $document, User $actor, string $reason): FiscalDocument
    {
        $reason = trim($reason);

        if (Str::length($reason) < 15 || Str::length($reason) > 255) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'A justificativa deve ter entre 15 e 255 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($document, $actor, $reason): FiscalDocument {
            $locked = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);

            $this->assertAvailable($locked);
            $this->assertSupported($locked);

            if ($locked->status === 'cancellation_queued') {
                if (! hash_equals((string) $locked->cancellation_reason, $reason)) {
                    throw ValidationException::withMessages([
                        'cancellation_reason' => 'A justificativa não pode ser alterada depois que o cancelamento entra na fila.',
                    ]);
                }

                return $locked;
            }

            if (! in_array($locked->status, ['authorized', 'cancellation_failed'], true)) {
                throw ValidationException::withMessages([
                    'fiscal' => 'Somente um documento autorizado pode ser cancelado.',
                ]);
            }

            $this->assertCancellationWindow($locked);

            $retry = $locked->status === 'cancellation_failed';
            if ($retry && ! hash_equals((string) $locked->cancellation_reason, $reason)) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => 'A retentativa deve preservar a justificativa do primeiro pedido.',
                ]);
            }

            $fromStatus = $locked->status;
            $locked->update([
                'status' => 'cancellation_queued',
                'cancellation_reason' => $locked->cancellation_reason ?: $reason,
                'cancellation_requested_at' => $locked->cancellation_requested_at ?: now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ]);
            $this->event($locked, 'cancellation_queued', $fromStatus, 'cancellation_queued', $actor, [
                'provider' => $locked->provider,
                'reason_length' => Str::length($reason),
                'retry' => $retry,
            ]);

            DB::afterCommit(fn () => $this->dispatchCancellation($locked->id));

            return $locked->fresh(['events']);
        });
    }

    public function submit(int $documentId): ?FiscalDocument
    {
        $queued = FiscalDocument::withoutGlobalScopes()->find($documentId);
        if (! $queued || ! in_array($queued->status, ['queued', 'processing'], true)) {
            return null;
        }

        $this->assertAvailable($queued);
        $document = $this->beginSubmission($documentId);

        if (! $document) {
            return null;
        }

        return $this->applyProviderResponse($document->id, $this->provider->submit($document), 'submission');
    }

    public function synchronize(int $documentId): ?FiscalDocument
    {
        $document = FiscalDocument::withoutGlobalScopes()->find($documentId);

        if (! $document || ! in_array($document->status, self::RECONCILABLE_STATUSES, true)) {
            return null;
        }

        $this->assertAvailable($document);
        $this->assertSupported($document);

        return $this->applyProviderResponse($document->id, $this->provider->status($document), 'status');
    }

    public function resendWebhook(int $documentId): ?FiscalDocument
    {
        $document = FiscalDocument::withoutGlobalScopes()->find($documentId);

        if (! $document || ! in_array($document->status, self::WEBHOOK_RECOVERY_STATUSES, true)) {
            return null;
        }

        $this->assertAvailable($document);
        $this->assertSupported($document);
        $this->assertWebhookRecoveryAvailable();
        $this->provider->resendWebhook($document);

        return DB::transaction(function () use ($documentId): ?FiscalDocument {
            $locked = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->find($documentId);
            if (! $locked || ! in_array($locked->status, self::WEBHOOK_RECOVERY_STATUSES, true)) {
                return $locked;
            }

            $status = $locked->status;
            if ($locked->last_error_code === 'webhook_resend_failed') {
                $locked->update([
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);
            }
            $this->event($locked, 'webhook_resend_requested', $status, $status, null, [
                'provider' => $locked->provider,
            ]);

            return $locked->fresh(['events']);
        });
    }

    /** @return array{content: string, content_type: string, filename: string} */
    public function downloadArtifact(FiscalDocument $document, User $actor, string $artifact): array
    {
        $this->assertAvailable($document);
        $this->assertSupported($document);

        if (! in_array($document->status, ['authorized', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'fiscal' => 'Os arquivos fiscais ficam disponíveis somente após a autorização do documento.',
            ]);
        }

        if (! in_array($artifact, ['xml', 'danfe', 'danfse'], true)) {
            throw ValidationException::withMessages([
                'fiscal' => 'Tipo de arquivo fiscal inválido.',
            ]);
        }

        try {
            $download = $this->provider->downloadArtifact($document, $artifact);
        } catch (Throwable $exception) {
            $this->event($document, 'artifact_download_failed', $document->status, $document->status, $actor, [
                'artifact' => $artifact,
                'error_class' => Str::limit(class_basename($exception), 80, ''),
            ]);

            throw ValidationException::withMessages([
                'fiscal' => 'Não foi possível baixar o arquivo fiscal com segurança. Revise a integração e tente novamente.',
            ]);
        }

        $filename = $document->type.'-'.$document->ulid.'.'.$download['extension'];
        $this->event($document, 'artifact_downloaded', $document->status, $document->status, $actor, [
            'artifact' => $artifact,
            'size_bytes' => strlen($download['content']),
        ]);

        return [
            'content' => $download['content'],
            'content_type' => $download['content_type'],
            'filename' => $filename,
        ];
    }

    public function cancel(int $documentId): ?FiscalDocument
    {
        $document = FiscalDocument::withoutGlobalScopes()->find($documentId);

        if (! $document || $document->status !== 'cancellation_queued') {
            return null;
        }

        $this->assertAvailable($document);
        $this->assertSupported($document);

        $reason = trim((string) $document->cancellation_reason);
        if ($reason === '') {
            throw new \LogicException('A justificativa congelada do cancelamento não foi localizada.');
        }

        return $this->applyCancellationResponse(
            $document->id,
            $this->provider->cancel($document, $reason)
        );
    }

    /** @param array<string, mixed> $response */
    public function applyProviderResponse(int $documentId, array $response, string $source): FiscalDocument
    {
        $normalized = $this->normalizeResponse($response);

        return DB::transaction(function () use ($documentId, $normalized, $source): FiscalDocument {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->findOrFail($documentId);
            $this->assertSupported($document);
            $this->assertResponseIdentity($document, $normalized);

            $fromStatus = $document->status;
            $toStatus = $this->resolvedStatus($document->status, (string) ($normalized['status'] ?? ''));

            if ($this->isStaleProviderStatus($document->status, (string) ($normalized['status'] ?? ''), $toStatus)) {
                return $document;
            }

            $changes = $this->responseChanges($document, $normalized, $toStatus);

            if ($changes === []) {
                return $document;
            }

            $document->update($changes);
            $event = match ($source) {
                'webhook' => 'webhook_received',
                'status' => 'status_synchronized',
                default => 'provider_response',
            };

            $this->event($document, $event, $fromStatus, $toStatus, null, [
                'source' => in_array($source, ['submission', 'status', 'webhook'], true) ? $source : 'provider',
                'provider_status' => $normalized['status'] ?? null,
                'response_hash' => hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ]);

            return $document->fresh(['events']);
        });
    }

    public function markSubmissionFailed(int $documentId, string $errorClass): void
    {
        DB::transaction(function () use ($documentId, $errorClass): void {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->find($documentId);

            if (! $document || ! in_array($document->status, ['queued', 'processing'], true)) {
                return;
            }

            $fromStatus = $document->status;
            $document->update([
                'status' => 'failed',
                'last_error_code' => 'provider_communication_failed',
                'last_error_message' => 'Falha definitiva na comunicação com o provedor fiscal.',
            ]);
            $this->event($document, 'submission_failed', $fromStatus, 'failed', null, [
                'error_class' => Str::limit(class_basename($errorClass), 80, ''),
            ]);
        });
    }

    public function markSynchronizationFailed(int $documentId, string $errorClass): void
    {
        DB::transaction(function () use ($documentId, $errorClass): void {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->find($documentId);

            if (! $document || ! in_array($document->status, self::RECONCILABLE_STATUSES, true)) {
                return;
            }

            $status = $document->status;
            if ($status !== 'cancellation_failed') {
                $document->update([
                    'last_error_code' => 'provider_status_failed',
                    'last_error_message' => 'A última consulta ao provedor fiscal esgotou as tentativas. Revise a situação antes de tentar novamente.',
                ]);
            }
            $this->event($document, 'status_sync_failed', $status, $status, null, [
                'error_class' => Str::limit(class_basename($errorClass), 80, ''),
            ]);
        });
    }

    public function markCancellationFailed(int $documentId, string $errorClass): void
    {
        DB::transaction(function () use ($documentId, $errorClass): void {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->find($documentId);

            if (! $document || $document->status !== 'cancellation_queued') {
                return;
            }

            $document->update([
                'status' => 'cancellation_failed',
                'last_error_code' => 'provider_cancellation_failed',
                'last_error_message' => 'O cancelamento esgotou as tentativas de comunicação. Revise a situação no provedor antes de tentar novamente.',
            ]);
            $this->event($document, 'cancellation_failed', 'cancellation_queued', 'cancellation_failed', null, [
                'error_class' => Str::limit(class_basename($errorClass), 80, ''),
            ]);
        });
    }

    public function markWebhookResendFailed(int $documentId, string $errorClass): void
    {
        DB::transaction(function () use ($documentId, $errorClass): void {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->find($documentId);

            if (! $document || ! in_array($document->status, self::WEBHOOK_RECOVERY_STATUSES, true)) {
                return;
            }

            if ($document->status !== 'cancellation_failed') {
                $document->update([
                    'last_error_code' => 'webhook_resend_failed',
                    'last_error_message' => 'A solicitação de reenvio do webhook esgotou as tentativas. Use a consulta manual antes de tentar novamente.',
                ]);
            }
            $this->event($document, 'webhook_resend_failed', $document->status, $document->status, null, [
                'error_class' => Str::limit(class_basename($errorClass), 80, ''),
            ]);
        });
    }

    /** @param array<string, mixed> $response */
    private function applyCancellationResponse(int $documentId, array $response): FiscalDocument
    {
        $normalized = $this->normalizeResponse($response);

        return DB::transaction(function () use ($documentId, $normalized): FiscalDocument {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->findOrFail($documentId);
            $this->assertSupported($document);
            $this->assertResponseIdentity($document, $normalized);

            if ($document->status === 'cancelled') {
                return $document;
            }

            if (! in_array($document->status, ['cancellation_queued', 'cancellation_failed'], true)) {
                return $document;
            }

            $fromStatus = $document->status;
            $providerStatus = Str::lower(trim((string) ($normalized['status'] ?? '')));
            $cancelled = $providerStatus === 'cancelado';
            $toStatus = $cancelled ? 'cancelled' : 'cancellation_failed';
            $changes = [
                'status' => $toStatus,
                'response_payload' => $this->responsePayload($document, $normalized),
                'last_error_code' => null,
                'last_error_message' => null,
            ];

            if ($cancelled) {
                $changes['cancelled_at'] = $document->cancelled_at ?: now();
            } else {
                $changes['last_error_code'] = Str::limit(
                    (string) ($normalized['status_sefaz'] ?? ($providerStatus === 'erro_cancelamento' ? 'erro_cancelamento' : 'unexpected_cancel_status')),
                    60,
                    ''
                );
                $changes['last_error_message'] = Str::limit(
                    (string) ($normalized['mensagem_sefaz'] ?? 'O provedor não confirmou o cancelamento. Revise a situação antes de tentar novamente.'),
                    1000,
                    ''
                );
            }

            $document->update($changes);
            $this->event(
                $document,
                $cancelled ? 'cancellation_succeeded' : 'cancellation_failed',
                $fromStatus,
                $toStatus,
                null,
                [
                    'provider_status' => $normalized['status'] ?? null,
                    'response_hash' => hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                ]
            );

            return $document->fresh(['events']);
        });
    }

    private function beginSubmission(int $documentId): ?FiscalDocument
    {
        return DB::transaction(function () use ($documentId): ?FiscalDocument {
            $document = FiscalDocument::withoutGlobalScopes()->lockForUpdate()->find($documentId);

            if (! $document || ! in_array($document->status, ['queued', 'processing'], true)) {
                return null;
            }

            $this->assertSupported($document);

            if ($document->type === FiscalDocumentType::NFCE) {
                $requestPayload = $document->request_payload ?? [];
                $preparedAt = now('America/Sao_Paulo')->toIso8601String();
                data_set($requestPayload, 'focus_nfce.payload.data_emissao', $preparedAt);
                data_set($requestPayload, 'focus_nfce.transmission_prepared_at', $preparedAt);
                $document->update(['request_payload' => $requestPayload]);
            }

            if ($document->status === 'queued') {
                $document->update([
                    'status' => 'processing',
                    'submitted_at' => $document->submitted_at ?: now(),
                ]);
                $this->event($document, 'submission_started', 'queued', 'processing', null, [
                    'provider' => $document->provider,
                ]);
            }

            return $document->fresh();
        });
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function normalizeResponse(array $response): array
    {
        $normalized = collect($response)
            ->only(self::RESPONSE_FIELDS)
            ->filter(fn (mixed $value): bool => is_scalar($value))
            ->map(fn (mixed $value): string => Str::limit(trim((string) $value), 2000, ''))
            ->all();

        ksort($normalized);

        return $normalized;
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function responseChanges(FiscalDocument $document, array $response, string $toStatus): array
    {
        $changes = [];

        $responsePayload = $this->responsePayload($document, $response);
        if (($document->response_payload ?? []) !== $responsePayload) {
            $changes['response_payload'] = $responsePayload;
        }

        if (in_array($document->last_error_code, ['provider_status_failed', 'webhook_resend_failed'], true)) {
            $changes['last_error_code'] = null;
            $changes['last_error_message'] = null;
        }

        if ($document->status !== $toStatus) {
            $changes['status'] = $toStatus;
        }

        $fields = [
            'access_key' => Str::limit((string) (data_get($response, 'chave_nfe') ?: data_get($response, 'chave_nfse')), 60, ''),
            'protocol' => Str::limit((string) (data_get($response, 'numero_protocolo') ?: data_get($response, 'protocolo')), 80, ''),
        ];

        foreach ($fields as $field => $value) {
            if (filled($value) && $document->getAttribute($field) !== $value) {
                $changes[$field] = $value;
            }
        }

        if ($toStatus === 'authorized') {
            if (! $document->authorized_at) {
                $changes['authorized_at'] = now();
            }
            $changes['last_error_code'] = null;
            $changes['last_error_message'] = null;
        } elseif ($toStatus === 'cancelled') {
            if (! $document->cancelled_at) {
                $changes['cancelled_at'] = now();
            }
            $changes['last_error_code'] = null;
            $changes['last_error_message'] = null;
        } elseif ($document->status === 'cancellation_failed' && $this->providerStatus((string) ($response['status'] ?? '')) === 'authorized') {
            $changes['last_error_code'] = 'cancellation_not_confirmed';
            $changes['last_error_message'] = 'A consulta confirmou que o provedor ainda considera o documento autorizado.';
        } elseif ($toStatus === 'cancellation_failed') {
            $changes['last_error_code'] = Str::limit((string) data_get($response, 'status_sefaz', 'erro_cancelamento'), 60, '');
            $changes['last_error_message'] = Str::limit((string) data_get($response, 'mensagem_sefaz', 'O provedor não confirmou o cancelamento.'), 1000, '');
        } elseif ($toStatus === 'rejected') {
            $changes['last_error_code'] = Str::limit((string) data_get($response, 'status_sefaz', 'erro_autorizacao'), 60, '');
            $changes['last_error_message'] = Str::limit((string) data_get($response, 'mensagem_sefaz', 'Documento rejeitado pelo provedor fiscal.'), 1000, '');
        }

        return collect($changes)
            ->reject(fn (mixed $value, string $field): bool => $document->getAttribute($field) === $value)
            ->all();
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function responsePayload(FiscalDocument $document, array $response): array
    {
        foreach (['caminho_xml_nota_fiscal', 'caminho_danfe', 'caminho_xml_cancelamento'] as $field) {
            $existing = data_get($document->response_payload, $field);
            if (! array_key_exists($field, $response) && is_string($existing) && $existing !== '') {
                $response[$field] = $existing;
            }
        }

        ksort($response);

        return $response;
    }

    private function resolvedStatus(string $current, string $providerStatus): string
    {
        $desired = $this->providerStatus($providerStatus) ?? $current;

        if ($desired === $current) {
            return $current;
        }

        $allowed = match ($current) {
            'queued', 'failed' => ['processing', 'authorized', 'rejected', 'cancelled'],
            'processing' => ['authorized', 'rejected', 'cancelled'],
            'authorized' => ['cancelled'],
            'cancellation_queued' => ['cancelled', 'cancellation_failed'],
            'cancellation_failed' => ['cancelled'],
            default => [],
        };

        return in_array($desired, $allowed, true) ? $desired : $current;
    }

    private function providerStatus(string $providerStatus): ?string
    {
        return match (Str::lower(trim($providerStatus))) {
            'processando_autorizacao' => 'processing',
            'autorizado' => 'authorized',
            'erro_autorizacao' => 'rejected',
            'denegado' => 'rejected',
            'cancelado' => 'cancelled',
            'erro_cancelamento' => 'cancellation_failed',
            default => null,
        };
    }

    private function isStaleProviderStatus(string $current, string $providerStatus, string $resolved): bool
    {
        $reported = $this->providerStatus($providerStatus);

        if (in_array($current, ['cancellation_queued', 'cancellation_failed'], true) && $reported === 'authorized') {
            return false;
        }

        return $reported !== null && $reported !== $current && $resolved === $current;
    }

    private function assertAvailable(?FiscalDocument $document = null): void
    {
        if (! $this->isAvailable($document)) {
            throw ValidationException::withMessages([
                'fiscal' => 'A transmissão fiscal permanece bloqueada. Ative os controles de implantação e um provedor revisado antes de usar a fila.',
            ]);
        }
    }

    private function assertSupported(FiscalDocument $document): void
    {
        if (! $this->isSupported($document)) {
            throw ValidationException::withMessages([
                'fiscal' => 'O ciclo operacional aceita somente NFS-e de Niterói ou NFC-e modelo 65 do RJ pela Focus NFe em homologação.',
            ]);
        }
    }

    private function isSupported(FiscalDocument $document): bool
    {
        if ($document->environment !== 'homologation' || $document->provider !== FiscalProviderCatalog::FOCUS_NFE) {
            return false;
        }

        return ($document->type === FiscalDocumentType::NFSE
                && data_get($document->issuer_snapshot, 'municipality_code') === FiscalMunicipalityCatalog::NITEROI_IBGE_CODE)
            || ($document->type === FiscalDocumentType::NFCE
                && strtoupper((string) data_get($document->issuer_snapshot, 'address.state')) === 'RJ');
    }

    private function payloadRoot(FiscalDocument $document): string
    {
        return $document->type === FiscalDocumentType::NFCE ? 'focus_nfce' : 'focus_nfe';
    }

    private function assertCancellationWindow(FiscalDocument $document): void
    {
        if ($document->type !== FiscalDocumentType::NFCE || ! $document->authorized_at) {
            return;
        }

        $minutes = max(1, (int) config('fiscal.nfce.cancellation_window_minutes', 30));
        if ($document->authorized_at->copy()->addMinutes($minutes)->isPast()) {
            throw ValidationException::withMessages([
                'fiscal' => "O prazo operacional de {$minutes} minutos para cancelar esta NFC-e já terminou. Confirme a situação diretamente com a contabilidade e a SEFAZ-RJ.",
            ]);
        }
    }

    private function assertWebhookRecoveryAvailable(): void
    {
        $enabled = (bool) config('fiscal.focus_nfe.webhook.enabled', false);
        $token = (string) config('fiscal.focus_nfe.webhook.authorization_token', '');
        $header = (string) config('fiscal.focus_nfe.webhook.authorization_header', '');

        if (! $enabled || strlen($token) < 32 || strlen($token) > 512 || ! preg_match('/^[A-Za-z0-9-]+$/', $header)) {
            throw ValidationException::withMessages([
                'fiscal' => 'O reenvio exige que o webhook autenticado esteja habilitado no servidor.',
            ]);
        }
    }

    /** @param array<string, mixed> $response */
    private function assertResponseIdentity(FiscalDocument $document, array $response): void
    {
        $reference = trim((string) ($response['ref'] ?? ''));
        if ($reference !== '' && ! hash_equals((string) $document->provider_reference, $reference)) {
            throw ValidationException::withMessages([
                'fiscal' => 'A referência retornada pelo provedor não corresponde ao documento fiscal.',
            ]);
        }

        $receivedCnpj = preg_replace('/\D+/', '', (string) ($response['cnpj_emitente'] ?? $response['cnpj_prestador'] ?? '')) ?: '';
        $expectedCnpj = preg_replace('/\D+/', '', (string) data_get($document->issuer_snapshot, 'document')) ?: '';
        if ($receivedCnpj !== '' && ! hash_equals($expectedCnpj, $receivedCnpj)) {
            throw ValidationException::withMessages([
                'fiscal' => 'O emitente retornado pelo provedor não corresponde ao documento fiscal.',
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function event(
        FiscalDocument $document,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        ?User $actor,
        array $metadata
    ): void {
        FiscalDocumentEvent::withoutGlobalScopes()->create([
            'clinic_id' => $document->clinic_id,
            'fiscal_document_id' => $document->id,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function dispatchSubmission(int $documentId): void
    {
        SubmitFiscalDocumentJob::dispatch($documentId)
            ->onConnection((string) config('fiscal.queue.connection', 'database'))
            ->onQueue((string) config('fiscal.queue.name', 'fiscal'));
    }

    private function dispatchSynchronization(int $documentId): void
    {
        SyncFiscalDocumentStatusJob::dispatch($documentId)
            ->onConnection((string) config('fiscal.queue.connection', 'database'))
            ->onQueue((string) config('fiscal.queue.name', 'fiscal'));
    }

    private function dispatchCancellation(int $documentId): void
    {
        CancelFiscalDocumentJob::dispatch($documentId)
            ->onConnection((string) config('fiscal.queue.connection', 'database'))
            ->onQueue((string) config('fiscal.queue.name', 'fiscal'));
    }

    private function dispatchWebhookResend(int $documentId): void
    {
        ResendFiscalWebhookJob::dispatch($documentId)
            ->onConnection((string) config('fiscal.queue.connection', 'database'))
            ->onQueue((string) config('fiscal.queue.name', 'fiscal'));
    }
}
