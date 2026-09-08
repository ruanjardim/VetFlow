<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OperationsRuntimeProbeEvent;
use App\Support\Operations\ReleaseIdentityService;
use App\Support\Operations\RuntimeOperationsProbeService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class OperationsRuntimeProbeRunService
{
    public function __construct(
        private readonly RuntimeOperationsProbeService $probes,
        private readonly ReleaseIdentityService $releaseIdentity,
    ) {}

    /** @return array{available: bool, can_prepare: bool, items: array<int, array<string, mixed>>} */
    public function summary(User $user): array
    {
        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            return ['available' => false, 'can_prepare' => false, 'items' => []];
        }

        $latestEventIds = $this->scope($user, $sha)
            ->selectRaw('MAX(id)')
            ->groupBy('probe_id');
        $latestByProbe = OperationsRuntimeProbeEvent::query()
            ->whereIn('id', $latestEventIds)
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->values();
        $openWindowMinutes = max(5, (int) config('operations.runtime_probe.evidence_max_age_minutes', 180));
        $hasRecentOpenProbe = $latestByProbe->contains(
            fn (OperationsRuntimeProbeEvent $event): bool => in_array($event->event, ['prepared', 'pending'], true)
                && $event->occurred_at->isAfter(now()->subMinutes($openWindowMinutes))
        );

        return [
            'available' => true,
            'can_prepare' => ! $hasRecentOpenProbe,
            'items' => $latestByProbe->map(fn (OperationsRuntimeProbeEvent $event): array => [
                'probe_id' => $event->probe_id,
                'status' => $event->event,
                'status_label' => $this->statusLabel($event->event),
                'status_tone' => $this->statusTone($event->event),
                'detail' => $event->detail,
                'actor' => $event->actor?->name,
                'occurred_at' => $event->occurred_at,
                'can_verify' => in_array($event->event, ['prepared', 'pending'], true),
            ])->all(),
        ];
    }

    public function prepare(User $user): OperationsRuntimeProbeEvent
    {
        $sha = $this->requiredSha();

        if (! $this->summary($user)['can_prepare']) {
            throw new DomainException('Já existe um probe recente aguardando processamento ou verificação.');
        }

        try {
            $probe = $this->probes->prepare();
        } catch (Throwable $exception) {
            throw new DomainException('Não foi possível preparar o probe com a fila e o armazenamento atuais.', previous: $exception);
        }

        return $this->record($user, $sha, $probe['probe_id'], 'prepared', [
            'queue_connection' => $probe['queue_connection'],
            'queue_mode' => $probe['queue_mode'],
            'storage_disk' => $probe['storage_disk'],
            'detail' => 'Probe sintético enviado para a fila assíncrona.',
        ]);
    }

    public function verify(User $user, string $probeId): OperationsRuntimeProbeEvent
    {
        $sha = $this->requiredSha();
        $latest = $this->scope($user, $sha)
            ->where('probe_id', strtoupper($probeId))
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        if ($latest === null || ! in_array($latest->event, ['prepared', 'pending'], true)) {
            throw new DomainException('Este probe não está disponível para verificação nesta clínica e release.');
        }

        try {
            $evidence = $this->probes->verify($latest->probe_id);
            $this->probes->writeEvidence($evidence);
            $this->probes->cleanup($latest->probe_id);
        } catch (Throwable $exception) {
            $event = $exception->getMessage() === 'O probe ainda nao foi processado pela fila.'
                ? 'pending'
                : 'failed';
            $detail = $event === 'pending'
                ? 'A fila ainda não concluiu o processamento; tente verificar novamente.'
                : 'A verificação não comprovou o contexto operacional esperado.';

            $this->record($user, $sha, $latest->probe_id, $event, [
                'queue_connection' => $latest->queue_connection,
                'queue_mode' => $latest->queue_mode,
                'storage_disk' => $latest->storage_disk,
                'detail' => $detail,
            ]);

            throw new DomainException($detail, previous: $exception);
        }

        return $this->record($user, $sha, $latest->probe_id, 'verified', [
            'queue_connection' => $latest->queue_connection,
            'queue_mode' => $latest->queue_mode,
            'storage_disk' => $latest->storage_disk,
            'detail' => count($evidence['checks']).' verificações operacionais aprovadas.',
        ]);
    }

    private function requiredSha(): string
    {
        $sha = $this->releaseIdentity->sha();

        if ($sha === null) {
            throw new DomainException('Identifique o commit publicado antes de executar o probe.');
        }

        return $sha;
    }

    private function scope(User $user, string $sha): Builder
    {
        return OperationsRuntimeProbeEvent::query()
            ->where('environment', app()->environment())
            ->where('release_sha', $sha)
            ->where(function (Builder $query) use ($user): void {
                $user->clinic_id === null
                    ? $query->whereNull('clinic_id')
                    : $query->where('clinic_id', $user->clinic_id);
            });
    }

    /** @param array{queue_connection: string, queue_mode: string, storage_disk: string, detail: string} $context */
    private function record(
        User $user,
        string $sha,
        string $probeId,
        string $event,
        array $context,
    ): OperationsRuntimeProbeEvent {
        return OperationsRuntimeProbeEvent::query()->create([
            'clinic_id' => $user->clinic_id,
            'actor_user_id' => $user->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'probe_id' => strtoupper($probeId),
            'event' => $event,
            'queue_connection' => $context['queue_connection'],
            'queue_mode' => $context['queue_mode'],
            'storage_disk' => $context['storage_disk'],
            'detail' => $context['detail'],
            'occurred_at' => now(),
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'prepared' => 'Enviado à fila',
            'pending' => 'Aguardando fila',
            'verified' => 'Aprovado',
            default => 'Não aprovado',
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'verified' => 'success',
            'prepared', 'pending' => 'warning',
            default => 'danger',
        };
    }
}
