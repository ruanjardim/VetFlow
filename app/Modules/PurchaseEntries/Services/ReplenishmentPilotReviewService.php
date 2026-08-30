<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Models\User;
use App\Modules\PurchaseEntries\Models\ReplenishmentPilotReviewEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReplenishmentPilotReviewService
{
    public const DECISIONS = [
        'reviewed' => 'Revisado',
        'held' => 'Requer acompanhamento',
    ];

    public function __construct(
        private readonly ReplenishmentPurchaseHistoryService $history,
    ) {}

    /**
     * @param  array<string, mixed>|null  $stats
     * @return array<string, mixed>
     */
    public function state(User $user, string $period, ?array $stats = null): array
    {
        $stats ??= $this->history->summary($user, $period);
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
        $stats = $this->history->summary($user, $period);
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

    /**
     * The review evidence deliberately omits generation time, labels and free-form
     * purchase notes so only stable, allowlisted validation facts affect freshness.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function snapshot(User $user, array $stats): array
    {
        $report = $this->history->reportFromSummary($user, $stats);

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
