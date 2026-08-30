<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Models\User;
use App\Modules\PurchaseEntries\Models\ReplenishmentPilotReviewEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReplenishmentPilotReviewService
{
    public const DECISIONS = [
        'reviewed' => 'Revisado',
        'held' => 'Requer acompanhamento',
    ];

    public function __construct(
        private readonly ReplenishmentPurchaseHistoryService $purchaseHistory,
    ) {}

    /**
     * @param  array<string, mixed>|null  $stats
     * @return array<string, mixed>
     */
    public function state(User $user, string $period, ?array $stats = null): array
    {
        $stats ??= $this->purchaseHistory->summary($user, $period);
        $snapshot = $this->snapshot($user, $stats);
        $hash = $this->hash($snapshot);
        $event = $this->latest($user, $period);
        $current = $event !== null && hash_equals($event->evidence_hash, $hash);

        return [
            'status' => $this->status($event, $current),
            'decision' => $event === null ? null : [
                'decision' => $event->decision,
                'label' => self::DECISIONS[$event->decision] ?? 'Revisão',
                'note' => $event->note,
                'actor' => $event->actor?->name,
                'reviewed_at' => $event->reviewed_at,
                'current' => $current,
            ],
        ];
    }

    public function record(
        User $user,
        string $period,
        string $decision,
        ?string $note,
    ): ReplenishmentPilotReviewEvent {
        $stats = $this->purchaseHistory->summary($user, $period);
        $snapshot = $this->snapshot($user, $stats);

        return DB::transaction(fn (): ReplenishmentPilotReviewEvent => ReplenishmentPilotReviewEvent::query()->create([
            'clinic_id' => $user->clinic_id,
            'actor_user_id' => $user->id,
            'scope_label' => $stats['scope_label'],
            'period' => $period,
            'decision' => $decision,
            'evidence_snapshot' => $snapshot,
            'evidence_hash' => $this->hash($snapshot),
            'note' => filled($note) ? trim((string) $note) : null,
            'reviewed_at' => now(),
        ]));
    }

    public function history(
        User $user,
        ?string $period = null,
        ?string $decision = null,
    ): LengthAwarePaginator {
        $query = $this->scopedQuery($user)->with('actor:id,name');

        if (array_key_exists((string) $period, ReplenishmentPurchaseHistoryService::PERIODS)) {
            $query->where('period', $period);
        }

        if (array_key_exists((string) $decision, self::DECISIONS)) {
            $query->where('decision', $decision);
        }

        $events = $query
            ->latest('reviewed_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
        $currentHashes = $events->getCollection()
            ->pluck('period')
            ->unique()
            ->mapWithKeys(function (string $eventPeriod) use ($user): array {
                $stats = $this->purchaseHistory->summary($user, $eventPeriod);

                return [$eventPeriod => $this->hash($this->snapshot($user, $stats))];
            });

        $events->through(function (ReplenishmentPilotReviewEvent $event) use ($currentHashes): array {
            $currentHash = $currentHashes->get($event->period);
            $current = is_string($currentHash) && hash_equals($event->evidence_hash, $currentHash);

            return [
                'id' => $event->id,
                'scope_label' => $event->scope_label,
                'period' => $event->period,
                'period_label' => ReplenishmentPurchaseHistoryService::PERIODS[$event->period] ?? $event->period,
                'decision' => $event->decision,
                'decision_label' => self::DECISIONS[$event->decision] ?? 'Revisão',
                'decision_tone' => $event->decision === 'reviewed' ? 'success' : 'warning',
                'note' => $event->note,
                'actor' => $event->actor?->name,
                'reviewed_at' => $event->reviewed_at,
                'evidence_current' => $current,
                'evidence_label' => $current ? 'Evidência atual' : 'Evidência superada',
                'evidence_tone' => $current ? 'success' : 'warning',
            ];
        });

        return $events;
    }

    /**
     * The review evidence deliberately omits generation time, labels and free-form
     * purchase notes so only stable, allowlisted validation facts affect freshness.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function snapshot(User $user, array $stats): array
    {
        $report = $this->purchaseHistory->reportFromSummary($user, $stats);

        return [
            'schema_version' => 1,
            'scope' => [
                'clinic_id' => $report['scope']['clinic_id'],
                'period' => $report['scope']['period'],
            ],
            'metrics' => $report['metrics'],
            'maturity' => $report['maturity'],
            'products' => $report['products'],
        ];
    }

    private function latest(User $user, string $period): ?ReplenishmentPilotReviewEvent
    {
        return $this->scopedQuery($user)
            ->with('actor:id,name')
            ->where('period', $period)
            ->latest('reviewed_at')
            ->latest('id')
            ->first();
    }

    private function scopedQuery(User $user): Builder
    {
        return ReplenishmentPilotReviewEvent::query()
            ->where(function (Builder $query) use ($user): void {
                $user->clinic_id === null
                    ? $query->whereNull('clinic_id')
                    : $query->where('clinic_id', $user->clinic_id);
            });
    }

    /** @return array{key: string, label: string, tone: string, description: string} */
    private function status(?ReplenishmentPilotReviewEvent $event, bool $current): array
    {
        if ($event === null) {
            return [
                'key' => 'pending',
                'label' => 'Sem revisão do período',
                'tone' => 'muted-badge',
                'description' => 'Registre a leitura humana do relatório selecionado.',
            ];
        }

        if (! $current) {
            return [
                'key' => 'stale',
                'label' => 'Revisão superada',
                'tone' => 'warning',
                'description' => 'Os dados do período mudaram; registre uma nova revisão.',
            ];
        }

        return $event->decision === 'reviewed'
            ? [
                'key' => 'reviewed',
                'label' => 'Revisão atual',
                'tone' => 'success',
                'description' => 'A decisão corresponde ao relatório atual do período.',
            ]
            : [
                'key' => 'held',
                'label' => 'Acompanhamento pendente',
                'tone' => 'warning',
                'description' => 'O relatório atual exige acompanhamento humano.',
            ];
    }

    /** @param array<string, mixed> $snapshot */
    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
