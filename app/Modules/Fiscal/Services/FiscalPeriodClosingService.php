<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalPeriodClosure;
use App\Modules\Fiscal\Models\FiscalProfile;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalPeriodClosingService
{
    public const SCHEMA_VERSION = 1;

    public const HISTORY_STATES = [
        'current' => 'Vigente',
        'stale' => 'Superado',
    ];

    public function __construct(
        private readonly FiscalReconciliationService $reconciliation,
        private readonly AuditTrailService $audit,
    ) {}

    /** @param array<string, mixed>|null $reconciliation */
    public function evaluate(
        Clinic $clinic,
        FiscalProfile $profile,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?array $reconciliation = null,
    ): array {
        $reconciliation ??= $this->reconciliation->report($clinic->id, $profile, $from, $to);
        $blocking = collect($reconciliation['summary'])
            ->except(['authorized', 'return_reviewed'])
            ->filter(fn (array $item): bool => $item['count'] > 0)
            ->values();
        $evidence = $this->evidence($profile, $reconciliation);
        $fingerprint = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $latest = FiscalPeriodClosure::query()
            ->with('closedBy')
            ->where('clinic_id', $clinic->id)
            ->whereDate('period_from', $from->toDateString())
            ->whereDate('period_to', $to->toDateString())
            ->latest('occurred_at')
            ->latest('id')
            ->first();
        $latestCurrent = $latest !== null
            && hash_equals($fingerprint, (string) $latest->evidence_fingerprint);
        $reconciled = $blocking->isEmpty();

        return [
            'period' => $reconciliation['period'],
            'reconciled' => $reconciled,
            'can_close' => $reconciled && ! $latestCurrent,
            'blocking' => $blocking,
            'evidence_snapshot' => $evidence,
            'evidence_fingerprint' => $fingerprint,
            'latest_closure' => $latest,
            'latest_closure_current' => $latestCurrent,
            'history' => FiscalPeriodClosure::query()
                ->with('closedBy')
                ->where('clinic_id', $clinic->id)
                ->latest('occurred_at')
                ->latest('id')
                ->limit(10)
                ->get(),
            'total_sales' => $reconciliation['total_sales'],
            'total_cents' => $reconciliation['total_cents'],
            'authorized_count' => $reconciliation['summary']['authorized']['count'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function record(
        Clinic $clinic,
        FiscalProfile $profile,
        User $actor,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $data,
    ): FiscalPeriodClosure {
        return DB::transaction(function () use ($clinic, $profile, $actor, $from, $to, $data): FiscalPeriodClosure {
            Clinic::withoutGlobalScopes()
                ->whereKey($clinic->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedProfile = FiscalProfile::withoutGlobalScopes()
                ->where('clinic_id', $clinic->id)
                ->lockForUpdate()
                ->first() ?? $profile;
            $evaluation = $this->evaluate($clinic, $lockedProfile, $from, $to);
            if (! $evaluation['reconciled']) {
                throw ValidationException::withMessages([
                    'period' => 'O período possui pendências na conciliação e ainda não pode ser fechado.',
                ]);
            }

            if ($evaluation['latest_closure_current']) {
                throw ValidationException::withMessages([
                    'period' => 'Este período já possui um fechamento vigente para a evidência atual.',
                ]);
            }

            $closure = FiscalPeriodClosure::query()->create([
                'clinic_id' => $clinic->id,
                'closed_by_user_id' => $actor->id,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'reviewer_name' => trim($data['reviewer_name']),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'schema_version' => self::SCHEMA_VERSION,
                'evidence_fingerprint' => $evaluation['evidence_fingerprint'],
                'evidence_snapshot' => $evaluation['evidence_snapshot'],
                'occurred_at' => now(),
            ]);

            $this->audit->record(
                'fiscal.period-closure.recorded',
                $closure,
                [],
                [
                    'period_from' => $closure->period_from->toDateString(),
                    'period_to' => $closure->period_to->toDateString(),
                    'reviewer_name' => $closure->reviewer_name,
                    'sales_count' => $evaluation['total_sales'],
                    'total_cents' => $evaluation['total_cents'],
                    'schema_version' => $closure->schema_version,
                ],
                $actor,
                clinicId: (int) $clinic->id,
                subjectLabel: 'Fechamento fiscal operacional'
            );

            return $closure;
        });
    }

    /** @return array<string, mixed> */
    public function dossier(
        Clinic $clinic,
        FiscalProfile $profile,
        FiscalPeriodClosure $closure,
    ): array {
        abort_unless((int) $closure->clinic_id === (int) $clinic->id, 404);

        $closure->loadMissing('closedBy');
        $evaluation = $this->evaluate(
            $clinic,
            $profile,
            CarbonImmutable::parse($closure->period_from),
            CarbonImmutable::parse($closure->period_to),
        );
        $isCurrent = hash_equals(
            (string) $evaluation['evidence_fingerprint'],
            (string) $closure->evidence_fingerprint,
        );
        $report = [
            'schema_version' => 'fiscal-period-closure-dossier-v1',
            'clinic' => [
                'id' => (int) $clinic->id,
                'name' => $clinic->trade_name ?: $clinic->corporate_name,
                'corporate_name' => $clinic->corporate_name,
                'cnpj' => $clinic->cnpj,
            ],
            'scope' => [
                'purpose' => 'operational_fiscal_period_closure',
                'operational_only' => true,
                'automatic_fiscal_action' => false,
                'accounting_or_tax_certification' => false,
            ],
            'period' => [
                'from' => $closure->period_from->toDateString(),
                'to' => $closure->period_to->toDateString(),
            ],
            'status' => [
                'current' => $isCurrent,
                'current_reconciliation_complete' => $evaluation['reconciled'],
            ],
            'closure' => [
                'id' => $closure->ulid,
                'reviewer_name' => $closure->reviewer_name,
                'recorded_by' => $closure->closedBy?->name,
                'notes' => $closure->notes,
                'occurred_at' => $closure->occurred_at?->toIso8601String(),
                'schema_version' => (int) $closure->schema_version,
            ],
            'status_labels' => FiscalReconciliationService::STATUS_LABELS,
            'evidence' => $closure->evidence_snapshot,
            'fingerprints' => [
                'recorded_evidence' => $closure->evidence_fingerprint,
                'current_evidence' => $evaluation['evidence_fingerprint'],
                'matches_current' => $isCurrent,
            ],
        ];
        $report['report_hash'] = hash('sha256', json_encode(
            $report,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
        $report['generated_at'] = now()->toIso8601String();

        return $report;
    }

    /**
     * @param  array{reviewer?: string|null, state?: string|null}  $filters
     * @return array{paginator: LengthAwarePaginator, summary: array{total: int, current: int, stale: int, total_cents: int}}
     */
    public function history(
        Clinic $clinic,
        FiscalProfile $profile,
        CarbonImmutable $competence,
        array $filters,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $monthStart = $competence->startOfMonth();
        $monthEnd = $competence->endOfMonth();
        $reviewer = trim((string) ($filters['reviewer'] ?? ''));
        $closures = FiscalPeriodClosure::query()
            ->with('closedBy')
            ->where('clinic_id', $clinic->id)
            ->whereDate('period_to', '>=', $monthStart->toDateString())
            ->whereDate('period_from', '<=', $monthEnd->toDateString())
            ->when($reviewer !== '', function ($query) use ($reviewer): void {
                $query->where(function ($query) use ($reviewer): void {
                    $query->where('reviewer_name', 'like', '%'.$reviewer.'%')
                        ->orWhereHas('closedBy', fn ($query) => $query->where('name', 'like', '%'.$reviewer.'%'));
                });
            })
            ->latest('occurred_at')
            ->latest('id')
            ->get();
        $evaluations = [];
        $rows = $closures->map(function (FiscalPeriodClosure $closure) use ($clinic, $profile, &$evaluations): array {
            $key = $closure->period_from->toDateString().'|'.$closure->period_to->toDateString();
            $evaluations[$key] ??= $this->evaluate(
                $clinic,
                $profile,
                CarbonImmutable::parse($closure->period_from),
                CarbonImmutable::parse($closure->period_to),
            );
            $evaluation = $evaluations[$key];
            $current = hash_equals(
                (string) $evaluation['evidence_fingerprint'],
                (string) $closure->evidence_fingerprint,
            );

            return [
                'closure' => $closure,
                'state' => $current ? 'current' : 'stale',
                'state_label' => self::HISTORY_STATES[$current ? 'current' : 'stale'],
                'current_reconciliation_complete' => $evaluation['reconciled'],
                'sales_count' => (int) data_get($closure->evidence_snapshot, 'totals.sales_count', 0),
                'total_cents' => (int) data_get($closure->evidence_snapshot, 'totals.total_cents', 0),
            ];
        });
        $summary = [
            'total' => $rows->count(),
            'current' => $rows->where('state', 'current')->count(),
            'stale' => $rows->where('state', 'stale')->count(),
            'total_cents' => $rows->sum('total_cents'),
        ];
        $state = $filters['state'] ?? null;
        if (array_key_exists((string) $state, self::HISTORY_STATES)) {
            $rows = $rows->where('state', $state)->values();
        }
        $page = max(1, $page);

        return [
            'paginator' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
            ),
            'summary' => $summary,
        ];
    }

    /** @param array<string, mixed> $report */
    private function evidence(FiscalProfile $profile, array $report): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'period' => $report['period'],
            'profile' => [
                'configuration_version' => (int) ($profile->configuration_version ?: 1),
                'enabled' => (bool) $profile->enabled,
                'environment' => $profile->environment,
                'provider' => $profile->provider,
                'nfce_enabled' => (bool) $profile->nfce_enabled,
                'nfse_enabled' => (bool) $profile->nfse_enabled,
            ],
            'totals' => [
                'sales_count' => $report['total_sales'],
                'total_cents' => $report['total_cents'],
                'statuses' => collect($report['summary'])->map(fn (array $item): array => [
                    'count' => $item['count'],
                    'total_cents' => $item['total_cents'],
                ])->all(),
            ],
            'sales' => $report['rows']->map(fn (array $row): array => [
                'sale_id' => $row['sale_id'],
                'sale_code' => $row['sale_code'],
                'completed_at' => $row['completed_at'],
                'sale_total_cents' => $row['sale_total_cents'],
                'expected_types' => $row['expected_types'],
                'status' => $row['status'],
                'documents' => collect($row['documents'])->map(fn (array $document): array => [
                    'id' => $document['id'],
                    'type' => $document['type'],
                    'status' => $document['status'],
                    'total_cents' => $document['total_cents'],
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
