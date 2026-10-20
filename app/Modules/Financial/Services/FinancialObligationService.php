<?php

namespace App\Modules\Financial\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialObligationService
{
    public const TYPE_LABELS = [
        'all' => 'Entradas e saídas',
        'income' => 'Contas a receber',
        'expense' => 'Contas a pagar',
    ];

    public const STATUS_LABELS = [
        'all' => 'Todas as situações',
        'open' => 'Em aberto',
        'overdue' => 'Vencidas',
        'today' => 'Vencem hoje',
        'upcoming' => 'A vencer',
        'paid' => 'Pagas ou recebidas',
        'cancelled' => 'Canceladas',
    ];

    public const AGING_LABELS = [
        'all' => 'Todas as faixas',
        '1_5' => 'Até 5 dias',
        '6_15' => '6 a 15 dias',
        '16_30' => '16 a 30 dias',
        '31_60' => '31 a 60 dias',
        '61_plus' => 'Acima de 60 dias',
    ];

    public const REVIEW_LABELS = [
        'reviewed' => 'Revisado',
        'follow_up' => 'Requer acompanhamento',
    ];

    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * @return array{
     *   filters: array{from: string, to: string, type: string, status: string, aging: string, clinic_id: int},
     *   reference: array{from: CarbonImmutable, to: CarbonImmutable},
     *   stats: array<string, int>,
     *   aging_buckets: Collection<int, array<string, int|string>>,
     *   transactions: LengthAwarePaginator,
     *   review_history: Collection<int, array<string, mixed>>,
     *   excluded_without_due_date: int
     * }
     */
    public function report(
        int $clinicId,
        ?string $from = null,
        ?string $to = null,
        ?string $type = null,
        ?string $status = null,
        ?string $aging = null,
    ): array {
        $filters = $this->normalizeFilters($clinicId, $from, $to, $type, $status, $aging);
        $today = CarbonImmutable::today();
        $baseQuery = $this->baseQuery($filters);
        $excludedWithoutDueDate = (clone $baseQuery)
            ->whereNull('due_date')
            ->where('status', '!=', 'cancelled')
            ->count();
        $periodQuery = $this->periodQuery($baseQuery, $filters);
        $transactionsForStats = (clone $periodQuery)->get(['id', 'type', 'amount', 'status', 'due_date']);
        $transactions = $this->orderedRowsQuery(
            $this->applyAging(
                $this->applyStatus((clone $periodQuery), $filters['status'], $today),
                $filters['aging'],
                $today,
            )
        )
            ->with(['category', 'supplier', 'purchaseEntry', 'sale'])
            ->paginate(30)
            ->withQueryString();

        return [
            'filters' => $filters,
            'reference' => [
                'from' => CarbonImmutable::createFromFormat('Y-m-d', $filters['from']),
                'to' => CarbonImmutable::createFromFormat('Y-m-d', $filters['to']),
            ],
            'stats' => $this->stats($transactionsForStats, $today),
            'aging_buckets' => $this->agingBuckets($transactionsForStats, $today),
            'transactions' => $transactions,
            'review_history' => $this->reviewHistory($clinicId),
            'excluded_without_due_date' => $excludedWithoutDueDate,
        ];
    }

    /**
     * @return array{
     *   filters: array{from: string, to: string, type: string, status: string, aging: string, clinic_id: int},
     *   rows: Collection<int, FinancialTransaction>
     * }
     */
    public function exportRows(
        int $clinicId,
        ?string $from = null,
        ?string $to = null,
        ?string $type = null,
        ?string $status = null,
        ?string $aging = null,
    ): array {
        $filters = $this->normalizeFilters($clinicId, $from, $to, $type, $status, $aging);
        $today = CarbonImmutable::today();
        $query = $this->periodQuery($this->baseQuery($filters), $filters);

        return [
            'filters' => $filters,
            'rows' => $this->orderedRowsQuery(
                $this->applyAging(
                    $this->applyStatus($query, $filters['status'], $today),
                    $filters['aging'],
                    $today,
                )
            )
                ->with(['category', 'supplier', 'sale'])
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{decision: string, note?: string|null}  $data
     */
    public function recordReview(int $clinicId, User $actor, array $filters, array $data): AuditEvent
    {
        $clinic = Clinic::query()->where('active', true)->findOrFail($clinicId);
        $report = $this->report(
            $clinicId,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['type'] ?? null,
            $filters['status'] ?? null,
            $filters['aging'] ?? null,
        );
        $snapshot = [
            'filters' => $report['filters'],
            'stats' => $report['stats'],
            'aging_buckets' => $report['aging_buckets']->map(fn (array $bucket): array => [
                'key' => $bucket['key'],
                'count' => $bucket['count'],
                'amount_cents' => $bucket['amount_cents'],
            ])->values()->all(),
        ];
        $event = $this->audit->record(
            'financial.obligations.reviewed',
            $clinic,
            [],
            [],
            $actor,
            [
                'decision' => $data['decision'],
                'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
                'filters' => $report['filters'],
                'summary' => $report['stats'],
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ],
            $clinicId,
            'Contas a pagar e receber'
        );

        abort_if(! $event, 500, 'Não foi possível registrar a revisão financeira.');

        return $event;
    }

    /** @param array<string, int|string> $filters */
    private function baseQuery(array $filters): Builder
    {
        return FinancialTransaction::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $filters['clinic_id'])
            ->when($filters['type'] !== 'all', fn (Builder $query): Builder => $query->where('type', $filters['type']));
    }

    /** @param array<string, int|string> $filters */
    private function periodQuery(Builder $query, array $filters): Builder
    {
        return $query->whereBetween('due_date', [$filters['from'], $filters['to']]);
    }

    private function orderedRowsQuery(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case when status in ('pending', 'overdue') then 0 when status = 'paid' then 1 else 2 end")
            ->orderBy('due_date')
            ->orderBy('id');
    }

    private function applyStatus(Builder $query, string $status, CarbonImmutable $today): Builder
    {
        return match ($status) {
            'open' => $query->whereIn('status', ['pending', 'overdue']),
            'overdue' => $query->whereIn('status', ['pending', 'overdue'])->whereDate('due_date', '<', $today),
            'today' => $query->whereIn('status', ['pending', 'overdue'])->whereDate('due_date', $today),
            'upcoming' => $query->whereIn('status', ['pending', 'overdue'])->whereDate('due_date', '>', $today),
            'paid' => $query->where('status', 'paid'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    private function applyAging(Builder $query, string $aging, CarbonImmutable $today): Builder
    {
        if ($aging === 'all') {
            return $query;
        }

        [$minimum, $maximum] = $this->agingRange($aging);
        $query
            ->whereIn('status', ['pending', 'overdue'])
            ->whereDate('due_date', '<=', $today->subDays($minimum));

        return $maximum === null
            ? $query
            : $query->whereDate('due_date', '>=', $today->subDays($maximum));
    }

    /** @return array{0: int, 1: int|null} */
    private function agingRange(string $aging): array
    {
        return match ($aging) {
            '1_5' => [1, 5],
            '6_15' => [6, 15],
            '16_30' => [16, 30],
            '31_60' => [31, 60],
            '61_plus' => [61, null],
            default => [1, null],
        };
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return Collection<int, array<string, int|string>>
     */
    private function agingBuckets(Collection $transactions, CarbonImmutable $today): Collection
    {
        $overdue = $transactions
            ->whereIn('status', ['pending', 'overdue'])
            ->filter(fn (FinancialTransaction $transaction): bool => $transaction->due_date?->lt($today) === true);

        return collect(array_keys(self::AGING_LABELS))
            ->reject(fn (string $key): bool => $key === 'all')
            ->map(function (string $key) use ($overdue, $today): array {
                [$minimum, $maximum] = $this->agingRange($key);
                $rows = $overdue->filter(function (FinancialTransaction $transaction) use ($today, $minimum, $maximum): bool {
                    $days = (int) $transaction->due_date->diffInDays($today);

                    return $days >= $minimum && ($maximum === null || $days <= $maximum);
                });

                return [
                    'key' => $key,
                    'label' => self::AGING_LABELS[$key],
                    'count' => $rows->count(),
                    'amount_cents' => $this->totalCents($rows),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array<string, int>
     */
    private function stats(Collection $transactions, CarbonImmutable $today): array
    {
        $active = $transactions->whereIn('status', ['pending', 'overdue']);
        $overdue = $active->filter(fn (FinancialTransaction $transaction): bool => $transaction->due_date?->lt($today) === true);
        $dueToday = $active->filter(fn (FinancialTransaction $transaction): bool => $transaction->due_date?->isSameDay($today) === true);
        $upcoming = $active->filter(fn (FinancialTransaction $transaction): bool => $transaction->due_date?->gt($today) === true);

        return [
            'open_cents' => $this->totalCents($active),
            'overdue_cents' => $this->totalCents($overdue),
            'due_today_cents' => $this->totalCents($dueToday),
            'upcoming_cents' => $this->totalCents($upcoming),
            'paid_cents' => $this->totalCents($transactions->where('status', 'paid')),
            'open_count' => $active->count(),
            'overdue_count' => $overdue->count(),
            'due_today_count' => $dueToday->count(),
            'upcoming_count' => $upcoming->count(),
            'paid_count' => $transactions->where('status', 'paid')->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function reviewHistory(int $clinicId): Collection
    {
        return AuditEvent::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->where('event', 'financial.obligations.reviewed')
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (AuditEvent $event): array => [
                'decision' => data_get($event->metadata, 'decision'),
                'decision_label' => self::REVIEW_LABELS[data_get($event->metadata, 'decision')] ?? 'Revisão registrada',
                'note' => data_get($event->metadata, 'note'),
                'filters' => data_get($event->metadata, 'filters', []),
                'summary' => data_get($event->metadata, 'summary', []),
                'actor_name' => $event->actor?->name ?: 'Sistema',
                'occurred_at' => $event->occurred_at,
            ]);
    }

    /** @return array{from: string, to: string, type: string, status: string, aging: string, clinic_id: int} */
    private function normalizeFilters(
        int $clinicId,
        ?string $from,
        ?string $to,
        ?string $type,
        ?string $status,
        ?string $aging,
    ): array {
        $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $from ?: today()->subYear()->startOfMonth()->toDateString());
        $toDate = CarbonImmutable::createFromFormat('Y-m-d', $to ?: today()->addMonthNoOverflow()->endOfMonth()->toDateString());

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'type' => array_key_exists((string) $type, self::TYPE_LABELS) ? (string) $type : 'all',
            'status' => array_key_exists((string) $status, self::STATUS_LABELS) ? (string) $status : 'open',
            'aging' => array_key_exists((string) $aging, self::AGING_LABELS) ? (string) $aging : 'all',
            'clinic_id' => $clinicId,
        ];
    }

    /** @param Collection<int, FinancialTransaction> $transactions */
    private function totalCents(Collection $transactions): int
    {
        return $transactions->sum(fn (FinancialTransaction $transaction): int => $this->cents($transaction->amount));
    }

    private function cents(mixed $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D+/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D+/', '', $fraction) ?: '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }
}
