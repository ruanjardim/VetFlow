<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OperationsBackupEvidenceEvent;
use App\Modules\Operations\Models\OperationsReleaseDecision;
use App\Modules\Operations\Models\OperationsRuntimeProbeEvent;
use App\Modules\Operations\Models\OperationsSmokeCheck;
use App\Support\Operations\ReleaseIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperationsHistoryService
{
    private const LIMIT = 100;

    public function __construct(private readonly ReleaseIdentityService $releaseIdentity) {}

    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     totals: array<string, int>,
     *     filters: array{type: ?string, release: string},
     *     current_release: ?string
     * }
     */
    public function timeline(User $user, ?string $type = null, string $release = 'current'): array
    {
        $sha = $this->releaseIdentity->sha();
        $allReleases = $release === 'all';
        $sources = [
            'runtime' => fn (): Collection => $this->runtimeItems($user, $sha, $allReleases),
            'backup' => fn (): Collection => $this->backupItems($user, $sha, $allReleases),
            'smoke' => fn (): Collection => $this->smokeItems($user, $sha, $allReleases),
            'decision' => fn (): Collection => $this->decisionItems($user, $sha, $allReleases),
        ];
        $collections = collect($sources)->map(
            fn (callable $source): Collection => $source(),
        );
        $totals = $collections->map(fn (Collection $items): int => $items->count())->all();
        $selected = $type === null ? array_keys($sources) : [$type];
        $items = collect($selected)
            ->flatMap(fn (string $source): Collection => $collections->get($source))
            ->sortByDesc(fn (array $item): int => $item['occurred_at']->getTimestamp())
            ->take(self::LIMIT)
            ->values()
            ->all();

        return [
            'items' => $items,
            'totals' => $totals,
            'filters' => ['type' => $type, 'release' => $allReleases ? 'all' : 'current'],
            'current_release' => $sha,
        ];
    }

    private function runtimeItems(User $user, ?string $sha, bool $allReleases): Collection
    {
        return $this->scope(OperationsRuntimeProbeEvent::query(), $user, $sha, $allReleases)
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (OperationsRuntimeProbeEvent $event): array => $this->item(
                'runtime',
                'Probe operacional',
                match ($event->event) {
                    'prepared' => 'Enviado à fila',
                    'pending' => 'Aguardando processamento',
                    'verified' => 'Verificado',
                    default => 'Não aprovado',
                },
                $event->detail,
                $event->actor?->name,
                $event->occurred_at,
                $event->release_sha,
            ));
    }

    private function backupItems(User $user, ?string $sha, bool $allReleases): Collection
    {
        return $this->scope(OperationsBackupEvidenceEvent::query(), $user, $sha, $allReleases)
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (OperationsBackupEvidenceEvent $event): array => $this->item(
                'backup',
                'Backup restaurável',
                $event->status === 'passed' ? 'Evidência aprovada' : 'Evidência reprovada',
                $event->backup_identifier.' · '.$event->checks_count.' verificações',
                $event->actor?->name,
                $event->occurred_at,
                $event->release_sha,
            ));
    }

    private function smokeItems(User $user, ?string $sha, bool $allReleases): Collection
    {
        return $this->scope(OperationsSmokeCheck::query(), $user, $sha, $allReleases)
            ->with('actor:id,name')
            ->latest('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (OperationsSmokeCheck $event): array => $this->item(
                'smoke',
                'Smoke test',
                $event->completed ? 'Item concluído' : 'Item reaberto',
                OperationsSmokeChecklistService::label($event->check_key),
                $event->actor?->name,
                $event->created_at,
                $event->release_sha,
            ));
    }

    private function decisionItems(User $user, ?string $sha, bool $allReleases): Collection
    {
        return $this->scope(OperationsReleaseDecision::query(), $user, $sha, $allReleases)
            ->with('actor:id,name')
            ->latest('decided_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (OperationsReleaseDecision $event): array => $this->item(
                'decision',
                'Decisão da release',
                $event->decision === 'approved' ? 'Release aprovada' : 'Release mantida em espera',
                'Decisão vinculada ao retrato das evidências daquele momento.',
                $event->actor?->name,
                $event->decided_at,
                $event->release_sha,
            ));
    }

    private function scope(Builder $query, User $user, ?string $sha, bool $allReleases): Builder
    {
        $query
            ->where('environment', app()->environment())
            ->where(function (Builder $query) use ($user): void {
                $user->clinic_id === null
                    ? $query->whereNull('clinic_id')
                    : $query->where('clinic_id', $user->clinic_id);
            });

        if (! $allReleases) {
            $sha === null
                ? $query->whereRaw('1 = 0')
                : $query->where('release_sha', $sha);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function item(
        string $type,
        string $typeLabel,
        string $action,
        string $summary,
        ?string $actor,
        Carbon $occurredAt,
        string $releaseSha,
    ): array {
        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'action' => $action,
            'summary' => $summary,
            'actor' => $actor,
            'occurred_at' => $occurredAt,
            'release_short' => substr($releaseSha, 0, 7),
        ];
    }
}
