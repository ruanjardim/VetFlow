<?php

namespace App\Modules\Fiscal\Services;

use App\Models\User;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDocumentEvent;
use App\Modules\Fiscal\Models\FiscalSaleAllocation;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalSaleAllocationService
{
    public function __construct(private readonly FiscalSaleAllocationCalculator $calculator) {}

    /** @return array<string, mixed> */
    public function review(Sale $sale): array
    {
        $sale->loadMissing(['items', 'payments.paymentMethod', 'fiscalDocuments', 'fiscalAllocation.approver']);

        if ($sale->fiscalAllocation) {
            return [
                'state' => 'approved',
                'can_approve' => false,
                'allocation' => $sale->fiscalAllocation,
                'snapshot' => $sale->fiscalAllocation->allocation_snapshot,
                'errors' => [],
            ];
        }

        if (! $sale->fiscalDocuments->contains(
            fn (FiscalDocument $document): bool => data_get($document->totals_snapshot, 'requires_allocation') === 'yes'
        )) {
            return [
                'state' => 'not_required',
                'can_approve' => false,
                'allocation' => null,
                'snapshot' => null,
                'errors' => [],
            ];
        }

        try {
            $this->validateDocuments($sale, $sale->fiscalDocuments);
            $source = $this->sourceSnapshot($sale);
            $snapshot = $this->calculator->calculate($source['items'], $source['payments'], $source['totals']);

            return [
                'state' => 'pending',
                'can_approve' => true,
                'allocation' => null,
                'snapshot' => $snapshot,
                'errors' => [],
            ];
        } catch (ValidationException $exception) {
            return [
                'state' => 'blocked',
                'can_approve' => false,
                'allocation' => null,
                'snapshot' => null,
                'errors' => collect($exception->errors())->flatten()->values()->all(),
            ];
        }
    }

    public function approve(Sale $sale, User $actor): FiscalSaleAllocation
    {
        return DB::transaction(function () use ($sale, $actor): FiscalSaleAllocation {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $existing = FiscalSaleAllocation::query()->where('sale_id', $sale->id)->first();
            if ($existing) {
                return $existing;
            }

            $sale->load(['items', 'payments.paymentMethod']);
            $documents = FiscalDocument::query()
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->get();
            $this->validateDocuments($sale, $documents);
            $source = $this->sourceSnapshot($sale);
            $snapshot = $this->calculator->calculate($source['items'], $source['payments'], $source['totals']);
            $fingerprint = hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            $allocation = FiscalSaleAllocation::query()->create([
                'clinic_id' => $sale->clinic_id,
                'sale_id' => $sale->id,
                'approved_by_user_id' => $actor->id,
                'schema_version' => FiscalSaleAllocationCalculator::SCHEMA_VERSION,
                'input_fingerprint' => $fingerprint,
                'source_snapshot' => $source,
                'allocation_snapshot' => $snapshot,
                'approved_at' => now(),
            ]);

            foreach ($documents as $document) {
                $documentAllocation = data_get($snapshot, 'documents.'.$document->type);
                if (! is_array($documentAllocation)) {
                    continue;
                }

                $this->applyToDocument($document, $allocation, $documentAllocation, $snapshot);
                FiscalDocumentEvent::query()->create([
                    'clinic_id' => $document->clinic_id,
                    'fiscal_document_id' => $document->id,
                    'actor_user_id' => $actor->id,
                    'event' => 'allocation_approved',
                    'from_status' => $document->status,
                    'to_status' => $document->status,
                    'metadata' => [
                        'allocation_ulid' => $allocation->ulid,
                        'allocation_schema_version' => $allocation->schema_version,
                        'input_fingerprint' => $fingerprint,
                        'document_total' => $documentAllocation['total'],
                    ],
                    'occurred_at' => now(),
                ]);
            }

            return $allocation->fresh(['approver', 'documents']);
        });
    }

    private function validateDocuments(Sale $sale, $documents): void
    {
        if ($sale->status !== 'completed') {
            $this->fail('Somente uma venda concluída pode ter rateio fiscal aprovado.');
        }

        $requiredTypes = $sale->items
            ->pluck('type')
            ->unique()
            ->map(fn (string $type): string => $type === 'product' ? 'nfce' : ($type === 'service' ? 'nfse' : 'invalid'))
            ->values();

        if ($requiredTypes->contains('invalid')) {
            $this->fail('A venda possui item avulso e não pode ser rateada fiscalmente.');
        }

        $preparedTypes = $documents->pluck('type')->unique();
        $missing = $requiredTypes->diff($preparedTypes)->values();
        if ($missing->isNotEmpty()) {
            $labels = $missing->map(fn (string $type): string => $type === 'nfce' ? 'NFC-e' : 'NFS-e')->implode(' e ');
            $this->fail("Prepare também {$labels} antes de revisar e aprovar o rateio.");
        }

        $relevantDocuments = $documents->whereIn('type', $requiredTypes);
        if ($relevantDocuments->count() !== $requiredTypes->count()) {
            $this->fail('Há mais de uma preparação do mesmo tipo para esta venda.');
        }

        if (! $relevantDocuments->contains(
            fn (FiscalDocument $document): bool => data_get($document->totals_snapshot, 'requires_allocation') === 'yes'
        )) {
            $this->fail('Esta venda não exige rateio fiscal.');
        }

        if ($relevantDocuments->contains(fn (FiscalDocument $document): bool => $document->status !== 'draft')) {
            $this->fail('O rateio só pode ser aprovado enquanto todos os documentos estão em rascunho.');
        }

        if ($relevantDocuments->contains(fn (FiscalDocument $document): bool => $document->request_payload !== null)) {
            $this->fail('Já existe payload fiscal gerado. O rateio não pode mais alterar os snapshots.');
        }

        foreach ($relevantDocuments as $document) {
            $itemType = $document->type === 'nfce' ? 'product' : 'service';
            $saleItems = $sale->items->where('type', $itemType)->keyBy('id');
            $documentItems = collect($document->items_snapshot)->keyBy('source_id');

            if ($saleItems->keys()->sort()->values()->all() !== $documentItems->keys()->sort()->values()->all()) {
                $this->fail('As linhas da venda mudaram depois da preparação fiscal. Prepare os documentos novamente antes do rateio.');
            }

            foreach ($documentItems as $sourceId => $itemSnapshot) {
                $saleItem = $saleItems->get($sourceId);
                if (! $saleItem
                    || $this->cents($saleItem->total) !== $this->cents($itemSnapshot['total'] ?? 0)
                    || $this->cents($saleItem->discount_total) !== $this->cents($itemSnapshot['discount_total'] ?? 0)) {
                    $this->fail('Os valores das linhas mudaram depois da preparação fiscal. Prepare os documentos novamente antes do rateio.');
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(Sale $sale): array
    {
        $items = $sale->items->sortBy('id')->map(fn ($item): array => [
            'source_id' => $item->id,
            'type' => $item->type,
            'base_total_cents' => $this->cents($item->total),
            'line_discount_cents' => $this->cents($item->discount_total),
        ])->values()->all();

        $payments = $sale->payments
            ->filter(fn ($payment): bool => ($payment->status ?? 'paid') === 'paid' && (float) $payment->amount > 0)
            ->sortBy('id')
            ->map(fn ($payment): array => [
                'source_id' => $payment->id,
                'method' => $payment->method,
                'method_name' => $payment->methodLabel(),
                'amount_cents' => $this->cents($payment->amount),
                'installments' => $payment->installments,
                'acquirer' => $payment->acquirer,
                'card_brand' => $payment->card_brand,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->values()->all();

        return [
            'sale_id' => $sale->id,
            'sale_code' => $sale->code,
            'completed_at' => $sale->completed_at?->toIso8601String(),
            'items' => $items,
            'payments' => $payments,
            'totals' => [
                'subtotal_cents' => $this->cents($sale->subtotal),
                'discount_total_cents' => $this->cents($sale->discount_total),
                'additions_total_cents' => $this->cents($sale->additions_total),
                'delivery_fee_cents' => $this->cents($sale->delivery_fee),
                'total_cents' => $this->cents($sale->total),
                'paid_total_cents' => $this->cents($sale->paid_total),
                'change_total_cents' => $this->cents($sale->change_total),
            ],
        ];
    }

    /** @param array<string, mixed> $documentAllocation @param array<string, mixed> $snapshot */
    private function applyToDocument(
        FiscalDocument $document,
        FiscalSaleAllocation $allocation,
        array $documentAllocation,
        array $snapshot
    ): void {
        $itemsBySource = collect($documentAllocation['items'])->keyBy('source_id');
        $items = collect($document->items_snapshot)->map(function (array $item) use ($itemsBySource): array {
            $allocated = $itemsBySource->get((int) $item['source_id']);
            if (! is_array($allocated)) {
                $this->fail('Uma linha do documento não foi encontrada no cálculo aprovado.');
            }

            $lineDiscount = (float) $allocated['line_discount_total'];
            $saleDiscount = (float) $allocated['allocated_sale_discount'];

            return array_replace($item, [
                'sale_line_total' => $allocated['sale_line_total'],
                'line_discount_total' => $allocated['line_discount_total'],
                'allocated_sale_discount' => $allocated['allocated_sale_discount'],
                'allocated_additions_total' => $allocated['allocated_additions_total'],
                'allocated_delivery_fee' => $allocated['allocated_delivery_fee'],
                'discount_total' => number_format($lineDiscount + $saleDiscount, 2, '.', ''),
                'total' => $allocated['total'],
            ]);
        })->values()->all();

        $totals = array_replace($document->totals_snapshot, [
            'selected_items_total' => $documentAllocation['selected_items_total'],
            'allocated_sale_discount' => $documentAllocation['allocated_sale_discount'],
            'additions_total' => $documentAllocation['allocated_additions_total'],
            'delivery_fee' => $documentAllocation['allocated_delivery_fee'],
            'total' => $documentAllocation['total'],
            'paid_total' => $documentAllocation['paid_total'],
            'change_total' => $documentAllocation['change_total'],
            'requires_allocation' => 'no',
            'allocation' => [
                'status' => 'approved',
                'ulid' => $allocation->ulid,
                'schema_version' => $allocation->schema_version,
                'rounding' => data_get($snapshot, 'rules.rounding'),
            ],
        ]);

        $document->update([
            'fiscal_sale_allocation_id' => $allocation->id,
            'schema_version' => 3,
            'items_snapshot' => $items,
            'payments_snapshot' => $documentAllocation['payments'],
            'totals_snapshot' => $totals,
        ]);
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['allocation' => $message]);
    }
}
