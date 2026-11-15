<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Models\User;
use App\Modules\PurchaseEntries\Models\PurchaseEntryItem;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

class ReplenishmentPurchaseHistoryService
{
    public const MATURITY_MIN_COMPARABLE_DECISIONS = 20;

    public const MATURITY_MIN_COMPARABLE_PRODUCTS = 5;

    public const MATURITY_MIN_EVIDENCE_PERCENT = 90.0;

    public const MATURITY_MIN_REASON_PERCENT = 100.0;

    public const DEFAULT_PERIOD = '90';

    public const PERIODS = [
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        '180' => 'Últimos 180 dias',
        'all' => 'Todo o histórico',
    ];

    public const CLASSIFICATIONS = [
        'kept' => 'Mantida',
        'adjusted' => 'Ajustada',
        'unavailable' => 'Comparação indisponível',
    ];

    public const PURCHASE_STATUSES = [
        'draft' => 'Rascunho',
        'received' => 'Recebida',
        'cancelled' => 'Cancelada',
    ];

    public function history(
        User $user,
        ?string $classification = null,
        ?string $purchaseStatus = null,
        ?string $search = null,
        string $period = self::DEFAULT_PERIOD,
    ): LengthAwarePaginator {
        $query = $this->scopedQuery($user, $period)->with([
            'product:id,name,unit',
            'purchaseEntry:id,clinic_id,supplier_id,code,status,purchased_at,received_at',
            'purchaseEntry.supplier' => fn ($supplierQuery) => $supplierQuery
                ->withTrashed()
                ->select(['id', 'name']),
        ]);

        if (array_key_exists((string) $classification, self::CLASSIFICATIONS)) {
            $query->where(
                'intelligence_metadata->replenishment_decision->classification',
                $classification,
            );
        }

        if (array_key_exists((string) $purchaseStatus, self::PURCHASE_STATUSES)) {
            $query->whereHas(
                'purchaseEntry',
                fn (Builder $entryQuery) => $entryQuery->where('status', $purchaseStatus),
            );
        }

        if (filled($search)) {
            $term = '%'.trim((string) $search).'%';
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery
                    ->where('description', 'like', $term)
                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->where('name', 'like', $term))
                    ->orWhereHas('purchaseEntry', fn (Builder $entryQuery) => $entryQuery->where('code', 'like', $term));
            });
        }

        $items = $query
            ->latest('purchase_entry_items.created_at')
            ->latest('purchase_entry_items.id')
            ->paginate(25)
            ->withQueryString();
        $supplierNames = $this->suggestedSupplierNames($items, $user);

        $items->through(fn (PurchaseEntryItem $item): array => $this->safeItem($item, $supplierNames));

        return $items;
    }

    /** @return array<string, mixed> */
    public function summary(User $user, string $period = self::DEFAULT_PERIOD): array
    {
        $stats = [
            'scope_label' => $user->clinic?->trade_name
                ?: $user->clinic?->corporate_name
                ?: 'Todas as clínicas acessíveis',
            'period' => $period,
            'period_label' => self::PERIODS[$period] ?? self::PERIODS[self::DEFAULT_PERIOD],
            'total' => 0,
            'comparable' => 0,
            'kept' => 0,
            'adjusted' => 0,
            'unavailable' => 0,
            'adherence_percent' => null,
            'quantity_adjusted' => 0,
            'unit_cost_adjusted' => 0,
            'supplier_adjusted' => 0,
            'adjustments_with_reason' => 0,
            'evidence_coverage_percent' => null,
            'adjustment_reason_coverage_percent' => null,
            'average_abs_quantity_delta_percent' => null,
            'average_abs_unit_cost_delta_percent' => null,
            'product_count' => 0,
            'comparable_product_count' => 0,
            'products' => [],
            'maturity' => [],
        ];
        $products = [];
        $quantityDeltaTotal = 0.0;
        $quantityDeltaSamples = 0;
        $unitCostDeltaTotal = 0.0;
        $unitCostDeltaSamples = 0;

        $this->scopedQuery($user, $period)
            ->with('product:id,name')
            ->select(['id', 'purchase_entry_id', 'product_id', 'description', 'intelligence_metadata'])
            ->lazyById(500)
            ->each(function (PurchaseEntryItem $item) use (
                &$stats,
                &$products,
                &$quantityDeltaTotal,
                &$quantityDeltaSamples,
                &$unitCostDeltaTotal,
                &$unitCostDeltaSamples,
            ): void {
                $stats['total']++;
                $productKey = $item->product_id === null
                    ? 'description:'.mb_strtolower(trim((string) $item->description))
                    : 'product:'.$item->product_id;
                $products[$productKey] ??= $this->emptyProductStats($item);
                $productStats = &$products[$productKey];
                $productStats['total']++;
                $decision = $this->decision($item);
                $classification = $decision['classification'] ?? null;
                $comparable = ($decision['evidence_status'] ?? null) === 'valid'
                    && in_array($classification, ['kept', 'adjusted'], true);

                if (! $comparable) {
                    $stats['unavailable']++;
                    $productStats['unavailable']++;

                    return;
                }

                $stats['comparable']++;
                $stats[$classification]++;
                $productStats['comparable']++;
                $productStats[$classification]++;

                if ($classification === 'adjusted') {
                    $reasonCode = $decision['adjustment_reason']['code'] ?? null;

                    if (is_string($reasonCode)
                        && array_key_exists($reasonCode, ReplenishmentPurchaseDecisionService::ADJUSTMENT_REASONS)) {
                        $stats['adjustments_with_reason']++;
                    }
                }

                if (($decision['quantity']['changed'] ?? false) === true) {
                    $stats['quantity_adjusted']++;
                    $productStats['quantity_adjusted']++;
                }

                if (($decision['unit_cost']['changed'] ?? false) === true) {
                    $stats['unit_cost_adjusted']++;
                    $productStats['unit_cost_adjusted']++;
                }

                if (($decision['supplier']['status'] ?? null) === 'changed') {
                    $stats['supplier_adjusted']++;
                    $productStats['supplier_adjusted']++;
                }

                $quantityDelta = $this->numeric($decision, 'quantity', 'delta_percent');

                if ($quantityDelta !== null) {
                    $quantityDeltaTotal += abs($quantityDelta);
                    $quantityDeltaSamples++;
                    $productStats['quantity_delta_total'] += abs($quantityDelta);
                    $productStats['quantity_delta_samples']++;
                }

                $unitCostDelta = $this->numeric($decision, 'unit_cost', 'delta_percent');

                if ($unitCostDelta !== null) {
                    $unitCostDeltaTotal += abs($unitCostDelta);
                    $unitCostDeltaSamples++;
                    $productStats['unit_cost_delta_total'] += abs($unitCostDelta);
                    $productStats['unit_cost_delta_samples']++;
                }
            });

        if ($stats['comparable'] > 0) {
            $stats['adherence_percent'] = round($stats['kept'] / $stats['comparable'] * 100, 1);
        }

        if ($stats['total'] > 0) {
            $stats['evidence_coverage_percent'] = round($stats['comparable'] / $stats['total'] * 100, 1);
        }

        if ($stats['adjusted'] > 0) {
            $stats['adjustment_reason_coverage_percent'] = round(
                $stats['adjustments_with_reason'] / $stats['adjusted'] * 100,
                1,
            );
        }

        if ($quantityDeltaSamples > 0) {
            $stats['average_abs_quantity_delta_percent'] = round($quantityDeltaTotal / $quantityDeltaSamples, 2);
        }

        if ($unitCostDeltaSamples > 0) {
            $stats['average_abs_unit_cost_delta_percent'] = round($unitCostDeltaTotal / $unitCostDeltaSamples, 2);
        }

        $stats['product_count'] = count($products);
        $stats['comparable_product_count'] = count(array_filter(
            $products,
            fn (array $product): bool => $product['comparable'] > 0,
        ));
        $stats['products'] = $this->finalizeProductStats($products);
        $stats['maturity'] = $this->maturity($stats);

        return $stats;
    }

    /** @return array<string, mixed> */
    public function report(User $user, string $period = self::DEFAULT_PERIOD): array
    {
        return $this->reportFromSummary($user, $this->summary($user, $period));
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function reportFromSummary(User $user, array $stats): array
    {
        return [
            'schema_version' => 1,
            'generated_at' => now()->utc()->toIso8601String(),
            'scope' => [
                'clinic_id' => $user->clinic_id,
                'label' => $stats['scope_label'],
                'period' => $stats['period'],
                'period_label' => $stats['period_label'],
            ],
            'metrics' => [
                'total' => $stats['total'],
                'comparable' => $stats['comparable'],
                'kept' => $stats['kept'],
                'adjusted' => $stats['adjusted'],
                'unavailable' => $stats['unavailable'],
                'adherence_percent' => $stats['adherence_percent'],
                'evidence_coverage_percent' => $stats['evidence_coverage_percent'],
                'adjustment_reason_coverage_percent' => $stats['adjustment_reason_coverage_percent'],
                'quantity_adjusted' => $stats['quantity_adjusted'],
                'unit_cost_adjusted' => $stats['unit_cost_adjusted'],
                'supplier_adjusted' => $stats['supplier_adjusted'],
                'average_abs_quantity_delta_percent' => $stats['average_abs_quantity_delta_percent'],
                'average_abs_unit_cost_delta_percent' => $stats['average_abs_unit_cost_delta_percent'],
                'product_count' => $stats['product_count'],
                'comparable_product_count' => $stats['comparable_product_count'],
            ],
            'maturity' => [
                'status' => $stats['maturity']['status'],
                'status_label' => $stats['maturity']['status_label'],
                'criteria_met' => $stats['maturity']['criteria_met'],
                'criteria_total' => $stats['maturity']['criteria_total'],
                'criteria' => $stats['maturity']['criteria'],
                'next_action' => $stats['maturity']['next_action'],
            ],
            'products' => $stats['products'],
            'disclaimer' => 'Relatório observacional para revisão humana. Não comprova significância estatística, não aprova fornecedores e não altera regras automaticamente.',
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function maturity(array $stats): array
    {
        $reasonCoverage = $stats['adjustment_reason_coverage_percent'];
        $criteria = [
            'decisions' => [
                'label' => 'Decisões comparáveis',
                'current' => $stats['comparable'],
                'target' => self::MATURITY_MIN_COMPARABLE_DECISIONS,
                'met' => $stats['comparable'] >= self::MATURITY_MIN_COMPARABLE_DECISIONS,
            ],
            'products' => [
                'label' => 'Produtos comparáveis',
                'current' => $stats['comparable_product_count'],
                'target' => self::MATURITY_MIN_COMPARABLE_PRODUCTS,
                'met' => $stats['comparable_product_count'] >= self::MATURITY_MIN_COMPARABLE_PRODUCTS,
            ],
            'evidence' => [
                'label' => 'Evidência válida',
                'current' => $stats['evidence_coverage_percent'],
                'target' => self::MATURITY_MIN_EVIDENCE_PERCENT,
                'met' => $stats['evidence_coverage_percent'] !== null
                    && $stats['evidence_coverage_percent'] >= self::MATURITY_MIN_EVIDENCE_PERCENT,
            ],
            'reasons' => [
                'label' => 'Motivos registrados',
                'current' => $reasonCoverage,
                'target' => self::MATURITY_MIN_REASON_PERCENT,
                'met' => ($stats['adjusted'] === 0
                    && $stats['comparable'] >= self::MATURITY_MIN_COMPARABLE_DECISIONS)
                    || ($reasonCoverage !== null && $reasonCoverage >= self::MATURITY_MIN_REASON_PERCENT),
            ],
        ];
        $criteriaMet = count(array_filter($criteria, fn (array $criterion): bool => $criterion['met']));
        $ready = $criteriaMet === count($criteria);

        return [
            'status' => $ready ? 'ready' : ($stats['total'] === 0 ? 'empty' : 'building'),
            'status_label' => $ready
                ? 'Base operacional atingida'
                : ($stats['total'] === 0 ? 'Sem amostra no período' : 'Amostra em formação'),
            'status_tone' => $ready ? 'success' : ($stats['total'] === 0 ? 'muted-badge' : 'warning'),
            'criteria_met' => $criteriaMet,
            'criteria_total' => count($criteria),
            'criteria' => $criteria,
            'next_action' => $this->maturityNextAction($stats, $criteria, $ready),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, array<string, mixed>>  $criteria
     */
    private function maturityNextAction(array $stats, array $criteria, bool $ready): string
    {
        if ($ready) {
            return 'A referência operacional inicial foi atingida. Mantenha a coleta e faça revisão humana antes de alterar qualquer regra.';
        }

        if (! $criteria['decisions']['met']) {
            $remaining = self::MATURITY_MIN_COMPARABLE_DECISIONS - $stats['comparable'];

            return "Registre mais {$remaining} decisão(ões) comparável(is) no período selecionado.";
        }

        if (! $criteria['products']['met']) {
            $remaining = self::MATURITY_MIN_COMPARABLE_PRODUCTS - $stats['comparable_product_count'];

            return "Inclua decisões comparáveis de mais {$remaining} produto(s).";
        }

        if (! $criteria['evidence']['met']) {
            return 'Revise as evidências indisponíveis até que ao menos 90% das decisões sejam comparáveis.';
        }

        $missingReasons = $stats['adjusted'] - $stats['adjustments_with_reason'];

        return "Complete o motivo de {$missingReasons} ajuste(s) legado(s).";
    }

    /** @return array<string, int|float|string|null> */
    private function emptyProductStats(PurchaseEntryItem $item): array
    {
        return [
            'name' => $item->product?->name ?: $item->description ?: 'Produto removido',
            'total' => 0,
            'comparable' => 0,
            'kept' => 0,
            'adjusted' => 0,
            'unavailable' => 0,
            'adherence_percent' => null,
            'adjustment_rate_percent' => null,
            'quantity_adjusted' => 0,
            'unit_cost_adjusted' => 0,
            'supplier_adjusted' => 0,
            'average_abs_quantity_delta_percent' => null,
            'average_abs_unit_cost_delta_percent' => null,
            'quantity_delta_total' => 0.0,
            'quantity_delta_samples' => 0,
            'unit_cost_delta_total' => 0.0,
            'unit_cost_delta_samples' => 0,
        ];
    }

    /**
     * @param  array<string, array<string, int|float|string|null>>  $products
     * @return array<int, array<string, int|float|string|null>>
     */
    private function finalizeProductStats(array $products): array
    {
        foreach ($products as &$product) {
            if ($product['comparable'] > 0) {
                $product['adherence_percent'] = round($product['kept'] / $product['comparable'] * 100, 1);
                $product['adjustment_rate_percent'] = round($product['adjusted'] / $product['comparable'] * 100, 1);
            }

            if ($product['quantity_delta_samples'] > 0) {
                $product['average_abs_quantity_delta_percent'] = round(
                    $product['quantity_delta_total'] / $product['quantity_delta_samples'],
                    2,
                );
            }

            if ($product['unit_cost_delta_samples'] > 0) {
                $product['average_abs_unit_cost_delta_percent'] = round(
                    $product['unit_cost_delta_total'] / $product['unit_cost_delta_samples'],
                    2,
                );
            }

            unset(
                $product['quantity_delta_total'],
                $product['quantity_delta_samples'],
                $product['unit_cost_delta_total'],
                $product['unit_cost_delta_samples'],
            );
        }
        unset($product);

        usort($products, function (array $left, array $right): int {
            return ($right['adjusted'] <=> $left['adjusted'])
                ?: ($right['comparable'] <=> $left['comparable'])
                ?: strcasecmp((string) $left['name'], (string) $right['name']);
        });

        return array_slice(array_values($products), 0, 10);
    }

    /** @return array<int, string> */
    private function suggestedSupplierNames(LengthAwarePaginator $items, User $user): array
    {
        $supplierIds = $items->getCollection()
            ->map(fn (PurchaseEntryItem $item): ?int => $this->suggestedSupplierId($item))
            ->filter()
            ->unique()
            ->values();

        if ($supplierIds->isEmpty()) {
            return [];
        }

        return Supplier::query()
            ->withTrashed()
            ->when(
                $user->clinic_id !== null,
                fn (Builder $query) => $query->where('clinic_id', $user->clinic_id),
            )
            ->whereIn('id', $supplierIds)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(int) $id => $name])
            ->all();
    }

    /** @param array<int, string> $supplierNames */
    private function safeItem(PurchaseEntryItem $item, array $supplierNames): array
    {
        $entry = $item->purchaseEntry;
        $decision = $this->decision($item);
        $classification = (string) ($decision['classification'] ?? 'unavailable');
        $classification = array_key_exists($classification, self::CLASSIFICATIONS)
            ? $classification
            : 'unavailable';
        $evidenceStatus = (string) ($decision['evidence_status'] ?? 'invalid');
        $suggestedSupplierId = $this->suggestedSupplierId($item);
        $adjustmentReasonCode = $decision['adjustment_reason']['code'] ?? null;
        $adjustmentReasonLabel = is_string($adjustmentReasonCode)
            ? (ReplenishmentPurchaseDecisionService::ADJUSTMENT_REASONS[$adjustmentReasonCode] ?? null)
            : null;
        $adjustmentReasonNote = $decision['adjustment_reason']['note'] ?? null;

        return [
            'entry_id' => $entry->id,
            'entry_code' => $entry->code,
            'entry_status' => $entry->status,
            'entry_status_label' => self::PURCHASE_STATUSES[$entry->status] ?? 'Rascunho',
            'entry_status_tone' => match ($entry->status) {
                'received' => 'success',
                'cancelled' => 'danger',
                default => 'warning',
            },
            'entry_date' => $entry->purchased_at,
            'edit_url' => route('purchase-entries.edit', $entry->id),
            'product_name' => $item->product?->name ?: $item->description ?: 'Produto removido',
            'product_unit' => $item->product?->unit ?: 'un',
            'classification' => $classification,
            'classification_label' => self::CLASSIFICATIONS[$classification],
            'classification_tone' => match ($classification) {
                'kept' => 'success',
                'adjusted' => 'warning',
                default => 'muted-badge',
            },
            'evidence_status' => $evidenceStatus,
            'evidence_label' => match ($evidenceStatus) {
                'valid' => 'Evidência válida',
                'scope_mismatch' => 'Evidência incompatível',
                default => 'Evidência inválida',
            },
            'evidence_tone' => $evidenceStatus === 'valid' ? 'success' : 'danger',
            'evaluated_at' => $this->date($decision['evaluated_at'] ?? null),
            'quantity_actual' => (float) $item->quantity,
            'quantity_suggested' => $this->numeric($decision, 'quantity', 'suggested'),
            'quantity_delta' => $this->numeric($decision, 'quantity', 'delta'),
            'quantity_delta_percent' => $this->numeric($decision, 'quantity', 'delta_percent'),
            'unit_cost_actual' => (float) $item->unit_cost,
            'unit_cost_suggested' => $this->numeric($decision, 'unit_cost', 'suggested'),
            'unit_cost_delta' => $this->numeric($decision, 'unit_cost', 'delta'),
            'unit_cost_delta_percent' => $this->numeric($decision, 'unit_cost', 'delta_percent'),
            'supplier_status' => $decision['supplier']['status'] ?? 'unavailable',
            'supplier_actual_name' => $entry->supplier?->name ?: 'Sem fornecedor',
            'supplier_suggested_name' => $suggestedSupplierId === null
                ? 'Sem fornecedor sugerido'
                : ($supplierNames[$suggestedSupplierId] ?? 'Fornecedor removido'),
            'adjustment_reason_label' => $adjustmentReasonLabel,
            'adjustment_reason_note' => is_string($adjustmentReasonNote) && trim($adjustmentReasonNote) !== ''
                ? trim($adjustmentReasonNote)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function decision(PurchaseEntryItem $item): array
    {
        $decision = $item->intelligence_metadata['replenishment_decision'] ?? null;

        return is_array($decision) ? $decision : [];
    }

    private function suggestedSupplierId(PurchaseEntryItem $item): ?int
    {
        $supplierId = $this->decision($item)['supplier']['suggested_id'] ?? null;

        return is_numeric($supplierId) ? (int) $supplierId : null;
    }

    /** @param array<string, mixed> $decision */
    private function numeric(array $decision, string $group, string $key): ?float
    {
        $value = $decision[$group][$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function scopedQuery(User $user, string $period): Builder
    {
        $periodDays = $period === 'all' ? null : (int) $period;

        return PurchaseEntryItem::query()
            ->where('intelligence_status', 'replenishment_suggestion')
            ->whereHas('purchaseEntry', function (Builder $entryQuery) use ($user, $periodDays): void {
                $entryQuery->when(
                    $user->clinic_id !== null,
                    fn (Builder $clinicQuery) => $clinicQuery->where('clinic_id', $user->clinic_id),
                );

                if ($periodDays !== null) {
                    $entryQuery->whereBetween('purchased_at', [
                        now()->subDays($periodDays)->startOfDay(),
                        now()->endOfDay(),
                    ]);
                }
            });
    }
}
