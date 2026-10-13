<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Jobs\ProcessNfceInutilizationJob;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use App\Modules\Fiscal\Models\FiscalNfceSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class FiscalNfceInutilizationService
{
    private const ACTIVE_STATUSES = ['queued', 'processing', 'authorized'];

    private const RESPONSE_FIELDS = [
        'status', 'status_sefaz', 'mensagem_sefaz', 'serie',
        'numero_inicial', 'numero_final', 'modelo', 'cnpj',
        'caminho_xml', 'protocolo_sefaz',
    ];

    public function __construct(
        private readonly FiscalProvider $provider,
        private readonly FiscalCredentialResolver $credentials,
        private readonly AuditTrailService $audit,
    ) {}

    public function isAvailable(FiscalProfile $profile): bool
    {
        if (! config('fiscal.external_http_enabled', false)
            || ! config('fiscal.submission_enabled', false)
            || (string) config('fiscal.queue.connection', 'sync') === 'sync'
            || trim((string) config('fiscal.queue.name', '')) === ''
            || $this->provider instanceof DisabledFiscalProvider
            || ! $this->credentials->isConfigured()
            || ! $profile->enabled
            || ! $profile->nfce_enabled
            || $profile->environment !== 'homologation'
            || $profile->provider !== FiscalProviderCatalog::FOCUS_NFE) {
            return false;
        }

        return $this->credentials->canResolve($this->credentialProbe(
            (int) $profile->clinic_id,
            (string) data_get($profile->metadata, 'provider_credential_reference')
        ));
    }

    /** @param array{number_start: int, number_end: int, justification: string} $data */
    public function queue(FiscalProfile $profile, Clinic $clinic, User $actor, array $data): FiscalNfceInutilization
    {
        $this->assertProfile($profile, $clinic);
        $this->assertAvailable($profile);

        $start = (int) $data['number_start'];
        $end = (int) $data['number_end'];
        $justification = trim($data['justification']);
        $series = (int) data_get($profile->metadata, 'focus_nfce.series');
        $issuerDocument = $this->digits($clinic->cnpj);
        $credentialReference = trim((string) data_get($profile->metadata, 'provider_credential_reference'));

        if ($start < 1 || $end < $start || $end > FiscalNfceSequenceService::MAX_NUMBER) {
            throw ValidationException::withMessages([
                'number_start' => 'Informe uma faixa válida entre 1 e 999.999.999.',
            ]);
        }

        if (Str::length($justification) < 15 || Str::length($justification) > 255) {
            throw ValidationException::withMessages([
                'justification' => 'A justificativa deve ter entre 15 e 255 caracteres.',
            ]);
        }

        return DB::transaction(function () use (
            $profile,
            $clinic,
            $actor,
            $start,
            $end,
            $justification,
            $series,
            $issuerDocument,
            $credentialReference
        ): FiscalNfceInutilization {
            $sequence = FiscalNfceSequence::withoutGlobalScopes()
                ->where('clinic_id', $clinic->id)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', 'homologation')
                ->where('series', $series)
                ->lockForUpdate()
                ->first();

            if (! $sequence || $end >= $sequence->next_number) {
                throw ValidationException::withMessages([
                    'number_end' => 'A faixa deve terminar antes do próximo número configurado da NFC-e.',
                ]);
            }

            $overlap = FiscalNfceInutilization::withoutGlobalScopes()
                ->where('clinic_id', $clinic->id)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', 'homologation')
                ->where('series', $series)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where('number_start', '<=', $end)
                ->where('number_end', '>=', $start)
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'number_start' => 'A faixa informada se sobrepõe a uma inutilização já solicitada.',
                ]);
            }

            $usedNumber = FiscalDocument::withoutGlobalScopes()
                ->where('clinic_id', $clinic->id)
                ->where('provider', FiscalProviderCatalog::FOCUS_NFE)
                ->where('environment', 'homologation')
                ->where('type', FiscalDocumentType::NFCE)
                ->where('series', (string) $series)
                ->whereNotNull('document_number')
                ->pluck('document_number')
                ->contains(fn (mixed $number): bool => (int) $number >= $start && (int) $number <= $end);

            if ($usedNumber) {
                throw ValidationException::withMessages([
                    'number_start' => 'A faixa contém um número já reservado para uma NFC-e do VetFlow.',
                ]);
            }

            $payload = [
                'cnpj' => $issuerDocument,
                'serie' => (string) $series,
                'numero_inicial' => (string) $start,
                'numero_final' => (string) $end,
                'justificativa' => $justification,
            ];
            $inutilization = FiscalNfceInutilization::withoutGlobalScopes()->create([
                'clinic_id' => $clinic->id,
                'requested_by_user_id' => $actor->id,
                'provider' => $profile->provider,
                'environment' => 'homologation',
                'status' => 'queued',
                'issuer_document' => $issuerDocument,
                'credential_reference' => $credentialReference,
                'series' => $series,
                'number_start' => $start,
                'number_end' => $end,
                'justification' => $justification,
                'request_payload' => $payload,
                'queued_at' => now(),
            ]);

            $this->record($inutilization, [], $this->snapshot($inutilization), $actor, 'fiscal.nfce-inutilization.queued');
            DB::afterCommit(fn () => $this->dispatch($inutilization->id));

            return $inutilization;
        });
    }

    public function process(int $inutilizationId): ?FiscalNfceInutilization
    {
        $inutilization = DB::transaction(function () use ($inutilizationId): ?FiscalNfceInutilization {
            $locked = FiscalNfceInutilization::withoutGlobalScopes()->lockForUpdate()->find($inutilizationId);

            if (! $locked || ! in_array($locked->status, ['queued', 'processing'], true)) {
                return null;
            }

            $this->assertRuntimeAvailable($locked);
            if ($locked->status === 'queued') {
                $before = $this->snapshot($locked);
                $locked->update(['status' => 'processing']);
                $this->record($locked, $before, $this->snapshot($locked), $locked->requester, 'fiscal.nfce-inutilization.processing');
            }

            return $locked->fresh();
        });

        if (! $inutilization) {
            return null;
        }

        return $this->applyResponse(
            $inutilization->id,
            $this->provider->inutilizeNfce($inutilization)
        );
    }

    /** @param array<string, mixed> $response */
    public function applyResponse(int $inutilizationId, array $response): FiscalNfceInutilization
    {
        $normalized = $this->normalizeResponse($response);

        return DB::transaction(function () use ($inutilizationId, $normalized): FiscalNfceInutilization {
            $inutilization = FiscalNfceInutilization::withoutGlobalScopes()->lockForUpdate()->findOrFail($inutilizationId);

            if (! in_array($inutilization->status, ['queued', 'processing'], true)) {
                return $inutilization;
            }

            $this->assertResponseIdentity($inutilization, $normalized);
            $status = match (Str::lower((string) ($normalized['status'] ?? ''))) {
                'autorizado' => 'authorized',
                'erro_autorizacao' => 'rejected',
                default => throw new LogicException('O provedor retornou uma situação desconhecida para a inutilização da NFC-e.'),
            };
            if ($status === 'authorized' && ! filled($normalized['protocolo_sefaz'] ?? null)) {
                throw new LogicException('O provedor não retornou o protocolo da inutilização autorizada.');
            }
            $before = $this->snapshot($inutilization);
            $changes = [
                'status' => $status,
                'response_payload' => $normalized,
                'protocol' => Str::limit((string) ($normalized['protocolo_sefaz'] ?? ''), 80, '') ?: null,
                'xml_path' => Str::limit((string) ($normalized['caminho_xml'] ?? ''), 2000, '') ?: null,
                'processed_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ];

            if ($status === 'rejected') {
                $changes['last_error_code'] = Str::limit((string) ($normalized['status_sefaz'] ?? 'erro_autorizacao'), 60, '');
                $changes['last_error_message'] = Str::limit((string) ($normalized['mensagem_sefaz'] ?? 'A SEFAZ não autorizou a inutilização.'), 1000, '');
            }

            $inutilization->update($changes);
            $this->record(
                $inutilization,
                $before,
                $this->snapshot($inutilization),
                $inutilization->requester,
                $status === 'authorized' ? 'fiscal.nfce-inutilization.authorized' : 'fiscal.nfce-inutilization.rejected',
                ['response_hash' => hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))]
            );

            return $inutilization->fresh('requester');
        });
    }

    public function markFailed(int $inutilizationId, string $errorClass): void
    {
        DB::transaction(function () use ($inutilizationId, $errorClass): void {
            $inutilization = FiscalNfceInutilization::withoutGlobalScopes()->lockForUpdate()->find($inutilizationId);

            if (! $inutilization || ! in_array($inutilization->status, ['queued', 'processing'], true)) {
                return;
            }

            $before = $this->snapshot($inutilization);
            $inutilization->update([
                'status' => 'failed',
                'processed_at' => now(),
                'last_error_code' => 'provider_communication_failed',
                'last_error_message' => 'A inutilização esgotou as tentativas de comunicação. Consulte a SEFAZ antes de solicitar novamente.',
            ]);
            $this->record(
                $inutilization,
                $before,
                $this->snapshot($inutilization),
                $inutilization->requester,
                'fiscal.nfce-inutilization.failed',
                ['error_class' => Str::limit(class_basename($errorClass), 80, '')]
            );
        });
    }

    /** @return Collection<int, FiscalNfceInutilization> */
    public function recentForClinic(int $clinicId): Collection
    {
        return FiscalNfceInutilization::query()
            ->with('requester')
            ->where('clinic_id', $clinicId)
            ->latest()
            ->limit(10)
            ->get();
    }

    private function assertProfile(FiscalProfile $profile, Clinic $clinic): void
    {
        $series = data_get($profile->metadata, 'focus_nfce.series');
        $credentialReference = trim((string) data_get($profile->metadata, 'provider_credential_reference'));

        if ((int) $profile->clinic_id !== (int) $clinic->id
            || ! $profile->enabled
            || ! $profile->nfce_enabled
            || $profile->environment !== 'homologation'
            || $profile->provider !== FiscalProviderCatalog::FOCUS_NFE
            || strtoupper((string) $clinic->state) !== 'RJ'
            || strlen($this->digits($clinic->cnpj)) !== 14
            || $series === null
            || $series === ''
            || $credentialReference === '') {
            throw ValidationException::withMessages([
                'number_start' => 'Complete a configuração da NFC-e do estabelecimento antes de inutilizar uma faixa.',
            ]);
        }
    }

    private function assertAvailable(FiscalProfile $profile): void
    {
        if (! $this->isAvailable($profile)) {
            throw ValidationException::withMessages([
                'number_start' => 'A inutilização externa permanece bloqueada. Habilite o adaptador, a credencial e a fila de homologação no servidor.',
            ]);
        }
    }

    private function assertRuntimeAvailable(FiscalNfceInutilization $inutilization): void
    {
        $probe = $this->credentialProbe((int) $inutilization->clinic_id, (string) $inutilization->credential_reference);

        if (! config('fiscal.external_http_enabled', false)
            || ! config('fiscal.submission_enabled', false)
            || (string) config('fiscal.queue.connection', 'sync') === 'sync'
            || trim((string) config('fiscal.queue.name', '')) === ''
            || $this->provider instanceof DisabledFiscalProvider
            || ! $this->credentials->isConfigured()
            || ! $this->credentials->canResolve($probe)) {
            throw new LogicException('A operação externa de inutilização da NFC-e não está disponível neste servidor.');
        }
    }

    private function credentialProbe(int $clinicId, string $reference): FiscalDocument
    {
        return new FiscalDocument([
            'clinic_id' => $clinicId,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'type' => FiscalDocumentType::NFCE,
            'issuer_snapshot' => [
                'provider_credential_reference' => $reference,
                'address' => ['state' => 'RJ'],
            ],
        ]);
    }

    /** @param array<string, mixed> $response @return array<string, string> */
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

    /** @param array<string, string> $response */
    private function assertResponseIdentity(FiscalNfceInutilization $inutilization, array $response): void
    {
        $expected = [
            'cnpj' => $inutilization->issuer_document,
            'modelo' => '65',
            'serie' => (string) $inutilization->series,
            'numero_inicial' => (string) $inutilization->number_start,
            'numero_final' => (string) $inutilization->number_end,
        ];

        foreach ($expected as $field => $value) {
            if (! filled($response[$field] ?? null) || ! hash_equals($value, (string) $response[$field])) {
                throw new LogicException('A resposta da inutilização não corresponde ao emitente ou à faixa solicitada.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(FiscalNfceInutilization $inutilization): array
    {
        return $inutilization->only([
            'provider', 'environment', 'status', 'series', 'number_start',
            'number_end', 'protocol', 'last_error_code', 'processed_at',
        ]);
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<string, mixed> $metadata */
    private function record(
        FiscalNfceInutilization $inutilization,
        array $before,
        array $after,
        ?User $actor,
        string $event,
        array $metadata = []
    ): void {
        $this->audit->record(
            $event,
            $inutilization,
            $before,
            $after,
            $actor,
            $metadata,
            (int) $inutilization->clinic_id,
            "Inutilização NFC-e série {$inutilization->series}: {$inutilization->number_start}-{$inutilization->number_end}"
        );
    }

    private function dispatch(int $inutilizationId): void
    {
        ProcessNfceInutilizationJob::dispatch($inutilizationId)
            ->onConnection((string) config('fiscal.queue.connection', 'database'))
            ->onQueue((string) config('fiscal.queue.name', 'fiscal'));
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
