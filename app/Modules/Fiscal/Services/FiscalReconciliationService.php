<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Sales\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FiscalReconciliationService
{
    public const STATUS_LABELS = [
        'action_required' => 'Ação necessária',
        'configuration_pending' => 'Configuração pendente',
        'no_preparation' => 'Sem preparação',
        'allocation_pending' => 'Rateio pendente',
        'payload_pending' => 'Payload pendente',
        'submission_pending' => 'Envio pendente',
        'processing' => 'Em processamento',
        'authorized' => 'Autorizada',
        'return_reviewed' => 'Devolução revisada',
    ];

    public function __construct(private readonly FiscalReturnReviewService $returnReviews) {}

    /** @return array<string, mixed> */
    public function report(
        int $clinicId,
        FiscalProfile $profile,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $status = null,
    ): array {
        $sales = Sale::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('status', ['completed', 'returned'])
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->with([
                'items:id,sale_id,product_id,petshop_service_id,type,description,quantity,returned_quantity,refunded_total',
                'fiscalDocuments:id,sale_id,type,status,request_payload,totals_snapshot,authorized_at,cancelled_at,created_at',
                'fiscalAllocation:id,sale_id',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get([
                'id', 'clinic_id', 'code', 'status', 'total', 'return_total', 'refunded_total',
                'completed_at',
            ]);
        $rows = $sales->map(fn (Sale $sale): array => $this->classify($sale, $profile));
        $summary = collect(array_keys(self::STATUS_LABELS))->mapWithKeys(function (string $key) use ($rows): array {
            $matching = $rows->where('status', $key);

            return [$key => [
                'label' => self::STATUS_LABELS[$key],
                'count' => $matching->count(),
                'total_cents' => $matching->sum('sale_total_cents'),
            ]];
        })->all();
        $filtered = $status ? $rows->where('status', $status)->values() : $rows->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'profile' => [
                'enabled' => (bool) $profile->enabled,
                'nfce_enabled' => (bool) $profile->nfce_enabled,
                'nfse_enabled' => (bool) $profile->nfse_enabled,
            ],
            'status_filter' => $status,
            'status_labels' => self::STATUS_LABELS,
            'summary' => $summary,
            'total_sales' => $rows->count(),
            'total_cents' => $rows->sum('sale_total_cents'),
            'filtered_count' => $filtered->count(),
            'rows' => $filtered,
        ];
    }

    /** @return array<string, mixed> */
    private function classify(Sale $sale, FiscalProfile $profile): array
    {
        $expectedTypes = $this->expectedTypes($sale);
        $unmappedCount = $sale->items->filter(function ($item): bool {
            $links = (int) filled($item->product_id) + (int) filled($item->petshop_service_id);

            return $links !== 1;
        })->count();
        $documents = $sale->fiscalDocuments
            ->whereIn('type', $expectedTypes)
            ->sortByDesc('created_at')
            ->unique('type')
            ->values();
        $documentsByType = $documents->keyBy('type');
        $missingTypes = collect($expectedTypes)->reject(fn (string $type): bool => $documentsByType->has($type))->values();
        $disabledTypes = collect($expectedTypes)->filter(fn (string $type): bool => ! $this->typeEnabled($profile, $type))->values();
        $returnReview = $this->hasReturn($sale) ? $this->returnReviews->evaluate($sale) : null;

        [$status, $detail, $nextAction] = $this->resolveStatus(
            $sale,
            $expectedTypes,
            $unmappedCount,
            $documents,
            $missingTypes,
            $disabledTypes,
            $returnReview,
        );

        return [
            'sale_id' => (int) $sale->id,
            'sale_code' => $sale->code,
            'completed_at' => $sale->completed_at?->toIso8601String(),
            'sale_total_cents' => $this->cents($sale->total),
            'expected_types' => $expectedTypes,
            'unmapped_items' => $unmappedCount,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'severity' => $this->severity($status),
            'detail' => $detail,
            'next_action' => $nextAction,
            'has_return' => $returnReview !== null,
            'return_review_current' => (bool) ($returnReview['latest_review_current'] ?? false),
            'return_review_resolved' => (bool) ($returnReview['resolved'] ?? false),
            'documents' => $documents->map(fn (FiscalDocument $document): array => [
                'id' => (int) $document->id,
                'type' => $document->type,
                'status' => $document->status,
                'total_cents' => $this->cents(data_get($document->totals_snapshot, 'total', 0)),
            ])->values()->all(),
        ];
    }

    /** @return array{string, string, string} */
    private function resolveStatus(
        Sale $sale,
        array $expectedTypes,
        int $unmappedCount,
        Collection $documents,
        Collection $missingTypes,
        Collection $disabledTypes,
        ?array $returnReview,
    ): array {
        if ($expectedTypes === [] || $unmappedCount > 0) {
            return [
                'action_required',
                $unmappedCount > 0
                    ? "{$unmappedCount} item(ns) sem vínculo fiscal inequívoco com produto ou serviço."
                    : 'A venda não possui linha vinculada a produto ou serviço fiscalizável pelo fluxo atual.',
                'Revise os itens da venda. A conciliação não presume o tipo fiscal de linhas avulsas ou ambíguas.',
            ];
        }

        if ($returnReview !== null) {
            if ($returnReview['resolved']) {
                return [
                    'return_reviewed',
                    'A devolução ou o reembolso possui revisão humana concluída e vigente.',
                    'Nenhuma pendência foi identificada neste controle interno. Siga a orientação registrada pela clínica e sua contabilidade.',
                ];
            }

            $latest = $returnReview['latest_review'];
            $detail = $latest === null
                ? 'A venda possui devolução ou reembolso ainda sem revisão fiscal humana.'
                : ($returnReview['latest_review_current']
                    ? 'A revisão atual ainda indica acompanhamento necessário.'
                    : 'A revisão anterior ficou desatualizada após mudança na devolução ou nos documentos.');

            return [
                'action_required',
                $detail,
                'Abra a revisão assistida, confira a evidência atual e registre a orientação recebida. O VetFlow não cria documento fiscal de devolução automaticamente.',
            ];
        }

        if ($disabledTypes->isNotEmpty()) {
            return [
                'configuration_pending',
                'O perfil atual não habilita '.implode(' e ', $this->typeLabels($disabledTypes->all())).'.',
                'Revise com a contabilidade quais tipos a clínica usará e então atualize o perfil de homologação.',
            ];
        }

        $problem = $documents->first(fn (FiscalDocument $document): bool => in_array(
            $document->status,
            ['rejected', 'failed', 'cancellation_failed', 'cancelled'],
            true
        ));
        if ($problem) {
            return [
                'action_required',
                strtoupper($problem->type).' está '.($problem->status === 'cancelled' ? 'cancelada' : 'com status '.$problem->status).'.',
                'Abra o documento, confira a resposta do provedor e defina a correção antes de preparar outra emissão.',
            ];
        }

        if ($missingTypes->isNotEmpty()) {
            return [
                'no_preparation',
                'Ainda não foi preparada '.implode(' nem ', $this->typeLabels($missingTypes->all())).'.',
                'Abra o comprovante da venda e prepare manualmente cada tipo aplicável após revisar os itens.',
            ];
        }

        $allocationPending = $documents->contains(fn (FiscalDocument $document): bool => data_get($document->totals_snapshot, 'requires_allocation') === 'yes')
            || (count($expectedTypes) > 1 && ! $sale->fiscalAllocation);
        if ($allocationPending) {
            return [
                'allocation_pending',
                'Os documentos ainda não compartilham um rateio fiscal aprovado.',
                'Revise os valores da NFC-e/NFS-e e aprove o rateio em centavos antes de gerar os payloads.',
            ];
        }

        $documentTotal = $documents->sum(fn (FiscalDocument $document): int => $this->cents(data_get($document->totals_snapshot, 'total', 0)));
        if ($documentTotal !== $this->cents($sale->total)) {
            return [
                'action_required',
                'A soma atual dos documentos não fecha com o total da venda.',
                'Não transmita. Revise a venda, os snapshots e o rateio antes de continuar.',
            ];
        }

        if ($documents->contains(fn (FiscalDocument $document): bool => $document->status === 'draft' && empty($document->request_payload))) {
            return [
                'payload_pending',
                'Há preparação sem payload fiscal gerado.',
                'Abra cada preparação, confira o snapshot e gere o payload local de homologação.',
            ];
        }

        if ($documents->contains(fn (FiscalDocument $document): bool => $document->status === 'draft')) {
            return [
                'submission_pending',
                'Os payloads estão preparados, mas ainda não entraram na fila de homologação.',
                'Revise os payloads e envie-os somente quando a conta Focus e os controles do servidor estiverem prontos.',
            ];
        }

        if ($documents->contains(fn (FiscalDocument $document): bool => in_array($document->status, ['queued', 'processing', 'cancellation_queued'], true))) {
            return [
                'processing',
                'Há documento aguardando processamento ou confirmação do provedor.',
                'Acompanhe a fila e use a consulta de situação se o processamento ultrapassar o prazo operacional.',
            ];
        }

        if ($documents->every(fn (FiscalDocument $document): bool => $document->status === 'authorized')) {
            return [
                'authorized',
                'Todos os tipos preparados estão autorizados e seus totais fecham com a venda.',
                'Nenhuma ação fiscal pendente foi identificada por esta conciliação operacional.',
            ];
        }

        return [
            'action_required',
            'A combinação atual de estados fiscais precisa de revisão.',
            'Abra os documentos vinculados e confira o histórico antes de qualquer nova ação.',
        ];
    }

    /** @return array<int, string> */
    private function expectedTypes(Sale $sale): array
    {
        $types = [];
        if ($sale->items->contains(fn ($item): bool => filled($item->product_id))) {
            $types[] = FiscalDocumentType::NFCE;
        }
        if ($sale->items->contains(fn ($item): bool => filled($item->petshop_service_id))) {
            $types[] = FiscalDocumentType::NFSE;
        }

        return $types;
    }

    private function typeEnabled(FiscalProfile $profile, string $type): bool
    {
        return $profile->exists
            && $profile->enabled
            && ($type === FiscalDocumentType::NFCE ? $profile->nfce_enabled : $profile->nfse_enabled);
    }

    /** @param array<int, string> $types @return array<int, string> */
    private function typeLabels(array $types): array
    {
        return array_map(fn (string $type): string => FiscalDocumentType::labels()[$type] ?? strtoupper($type), $types);
    }

    private function severity(string $status): string
    {
        return match ($status) {
            'authorized', 'return_reviewed' => 'success',
            'action_required' => 'danger',
            default => 'warning',
        };
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

    private function hasReturn(Sale $sale): bool
    {
        return $sale->status === 'returned'
            || $this->cents($sale->return_total) > 0
            || $this->cents($sale->refunded_total) > 0;
    }
}
