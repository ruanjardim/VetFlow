<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Fiscal\Models\FiscalReturnReview;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalReturnReviewService
{
    public const FOLLOW_UP = 'follow_up_required';

    public const COMPLETED = 'review_completed';

    public const SCHEMA_VERSION = 1;

    public const DECISION_LABELS = [
        self::FOLLOW_UP => 'Acompanhamento necessário',
        self::COMPLETED => 'Revisão concluída',
    ];

    public function __construct(private readonly AuditTrailService $audit) {}

    /** @return array<string, mixed> */
    public function evaluate(Sale $sale): array
    {
        $sale->loadMissing(['items', 'events', 'fiscalDocuments', 'fiscalReturnReviews.reviewer']);
        $hasReturn = $sale->status === 'returned'
            || $this->cents($sale->return_total) > 0
            || $this->cents($sale->refunded_total) > 0;
        $snapshot = $this->evidence($sale);
        $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $reviews = $sale->fiscalReturnReviews
            ->sortByDesc(fn (FiscalReturnReview $review): string => sprintf('%s-%020d', $review->occurred_at?->format('YmdHis.u'), $review->id))
            ->take(10)
            ->values();
        $latest = $reviews->first();
        $latestCurrent = $latest !== null
            && hash_equals($fingerprint, (string) $latest->evidence_fingerprint);

        return [
            'has_return' => $hasReturn,
            'evidence_snapshot' => $snapshot,
            'evidence_fingerprint' => $fingerprint,
            'latest_review' => $latest,
            'latest_review_current' => $latestCurrent,
            'resolved' => $hasReturn
                && $latestCurrent
                && $latest->decision === self::COMPLETED,
            'history' => $reviews,
        ];
    }

    /** @param array<string, mixed> $data */
    public function record(Sale $sale, User $actor, array $data): FiscalReturnReview
    {
        return DB::transaction(function () use ($sale, $actor, $data): FiscalReturnReview {
            $lockedSale = Sale::withoutGlobalScopes()
                ->withTrashed()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless((int) $lockedSale->clinic_id === (int) $sale->clinic_id, 404);

            $evaluation = $this->evaluate($lockedSale);
            if (! $evaluation['has_return']) {
                throw ValidationException::withMessages([
                    'sale' => 'A venda não possui devolução ou reembolso para revisão fiscal.',
                ]);
            }

            if ($evaluation['latest_review_current']
                && $evaluation['latest_review']->decision === $data['decision']) {
                throw ValidationException::withMessages([
                    'decision' => 'Esta decisão já está registrada para a evidência atual.',
                ]);
            }

            $review = FiscalReturnReview::query()->create([
                'clinic_id' => $lockedSale->clinic_id,
                'sale_id' => $lockedSale->id,
                'reviewed_by_user_id' => $actor->id,
                'decision' => $data['decision'],
                'reviewer_name' => trim($data['reviewer_name']),
                'guidance_source' => trim($data['guidance_source']),
                'resolution_summary' => trim($data['resolution_summary']),
                'schema_version' => self::SCHEMA_VERSION,
                'evidence_fingerprint' => $evaluation['evidence_fingerprint'],
                'evidence_snapshot' => $evaluation['evidence_snapshot'],
                'occurred_at' => now(),
            ]);

            $this->audit->record(
                'fiscal.return-review.recorded',
                $review,
                [],
                [
                    'sale_id' => $lockedSale->id,
                    'sale_code' => $lockedSale->code,
                    'decision' => $review->decision,
                    'reviewer_name' => $review->reviewer_name,
                    'return_total_cents' => $this->cents($lockedSale->return_total),
                    'refunded_total_cents' => $this->cents($lockedSale->refunded_total),
                    'schema_version' => $review->schema_version,
                ],
                $actor,
                clinicId: (int) $lockedSale->clinic_id,
                subjectLabel: 'Revisão fiscal da devolução '.$lockedSale->code
            );

            return $review;
        });
    }

    /** @return array<string, mixed> */
    public function dossier(Sale $sale, FiscalReturnReview $review): array
    {
        abort_unless(
            (int) $review->sale_id === (int) $sale->id
                && (int) $review->clinic_id === (int) $sale->clinic_id,
            404
        );

        $sale->loadMissing('clinic');
        $review->loadMissing('reviewer');
        $evaluation = $this->evaluate($sale);
        $isCurrent = hash_equals(
            (string) $evaluation['evidence_fingerprint'],
            (string) $review->evidence_fingerprint
        );
        $report = [
            'schema_version' => 'fiscal-return-dossier-v1',
            'clinic' => [
                'id' => (int) $sale->clinic_id,
                'name' => $sale->clinic?->trade_name ?: $sale->clinic?->corporate_name,
                'corporate_name' => $sale->clinic?->corporate_name,
                'cnpj' => $sale->clinic?->cnpj,
            ],
            'scope' => [
                'purpose' => 'assisted_return_review',
                'operational_only' => true,
                'automatic_fiscal_action' => false,
                'accounting_or_tax_certification' => false,
            ],
            'sale' => [
                'id' => (int) $sale->id,
                'code' => data_get($review->evidence_snapshot, 'sale.code', $sale->code),
            ],
            'status' => [
                'current' => $isCurrent,
                'completed' => $review->decision === self::COMPLETED,
                'operationally_resolved' => $isCurrent && $review->decision === self::COMPLETED,
            ],
            'review' => [
                'id' => $review->ulid,
                'decision' => $review->decision,
                'decision_label' => self::DECISION_LABELS[$review->decision] ?? $review->decision,
                'reviewer_name' => $review->reviewer_name,
                'recorded_by' => $review->reviewer?->name,
                'guidance_source' => $review->guidance_source,
                'resolution_summary' => $review->resolution_summary,
                'occurred_at' => $review->occurred_at?->toIso8601String(),
                'schema_version' => (int) $review->schema_version,
            ],
            'evidence' => $review->evidence_snapshot,
            'fingerprints' => [
                'recorded_evidence' => $review->evidence_fingerprint,
                'current_evidence' => $evaluation['evidence_fingerprint'],
                'matches_current' => $isCurrent,
            ],
        ];
        $report['report_hash'] = hash('sha256', json_encode(
            $report,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $report['generated_at'] = now()->toIso8601String();

        return $report;
    }

    /** @return array<string, mixed> */
    private function evidence(Sale $sale): array
    {
        $returnEventTypes = ['item_returned', 'refund', 'partial_return', 'returned'];

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'sale' => [
                'id' => (int) $sale->id,
                'code' => $sale->code,
                'status' => $sale->status,
                'completed_at' => $sale->completed_at?->toIso8601String(),
                'total_cents' => $this->cents($sale->total),
                'return_total_cents' => $this->cents($sale->return_total),
                'refunded_total_cents' => $this->cents($sale->refunded_total),
            ],
            'returned_items' => $sale->items
                ->filter(fn ($item): bool => (float) $item->returned_quantity > 0 || (float) $item->refunded_total > 0)
                ->sortBy('id')
                ->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'type' => $item->type,
                    'description' => $item->description,
                    'sold_quantity' => (string) $item->quantity,
                    'returned_quantity' => (string) $item->returned_quantity,
                    'refunded_total_cents' => $this->cents($item->refunded_total),
                ])->values()->all(),
            'return_events' => $sale->events
                ->whereIn('event_type', $returnEventTypes)
                ->sortBy('id')
                ->map(fn ($event): array => [
                    'id' => (int) $event->id,
                    'type' => $event->event_type,
                    'sale_item_id' => $event->sale_item_id ? (int) $event->sale_item_id : null,
                    'quantity' => $event->quantity !== null ? (string) $event->quantity : null,
                    'amount_cents' => $event->amount !== null ? $this->cents($event->amount) : null,
                    'reason' => $event->reason,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])->values()->all(),
            'documents' => $sale->fiscalDocuments
                ->sortBy('id')
                ->map(fn ($document): array => [
                    'id' => (int) $document->id,
                    'type' => $document->type,
                    'status' => $document->status,
                    'total_cents' => $this->cents(data_get($document->totals_snapshot, 'total', 0)),
                    'authorized_at' => $document->authorized_at?->toIso8601String(),
                    'cancelled_at' => $document->cancelled_at?->toIso8601String(),
                ])->values()->all(),
        ];
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
