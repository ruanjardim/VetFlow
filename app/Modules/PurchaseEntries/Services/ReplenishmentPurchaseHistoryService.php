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

    /** @return array<string, int|float|string|null> */
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
            'average_abs_quantity_delta_percent' => null,
            'average_abs_unit_cost_delta_percent' => null,
        ];
        $quantityDeltaTotal = 0.0;
        $quantityDeltaSamples = 0;
        $unitCostDeltaTotal = 0.0;
        $unitCostDeltaSamples = 0;

        $this->scopedQuery($user, $period)
            ->select(['id', 'purchase_entry_id', 'intelligence_metadata'])
            ->lazyById(500)
            ->each(function (PurchaseEntryItem $item) use (
                &$stats,
                &$quantityDeltaTotal,
                &$quantityDeltaSamples,
                &$unitCostDeltaTotal,
                &$unitCostDeltaSamples,
            ): void {
                $stats['total']++;
                $decision = $this->decision($item);
                $classification = $decision['classification'] ?? null;
                $comparable = ($decision['evidence_status'] ?? null) === 'valid'
                    && in_array($classification, ['kept', 'adjusted'], true);

                if (! $comparable) {
                    $stats['unavailable']++;

                    return;
                }

                $stats['comparable']++;
                $stats[$classification]++;

                if (($decision['quantity']['changed'] ?? false) === true) {
                    $stats['quantity_adjusted']++;
                }

                if (($decision['unit_cost']['changed'] ?? false) === true) {
                    $stats['unit_cost_adjusted']++;
                }

                if (($decision['supplier']['status'] ?? null) === 'changed') {
                    $stats['supplier_adjusted']++;
                }

                $quantityDelta = $this->numeric($decision, 'quantity', 'delta_percent');

                if ($quantityDelta !== null) {
                    $quantityDeltaTotal += abs($quantityDelta);
                    $quantityDeltaSamples++;
                }

                $unitCostDelta = $this->numeric($decision, 'unit_cost', 'delta_percent');

                if ($unitCostDelta !== null) {
                    $unitCostDeltaTotal += abs($unitCostDelta);
                    $unitCostDeltaSamples++;
                }
            });

        if ($stats['comparable'] > 0) {
            $stats['adherence_percent'] = round($stats['kept'] / $stats['comparable'] * 100, 1);
        }

        if ($quantityDeltaSamples > 0) {
            $stats['average_abs_quantity_delta_percent'] = round($quantityDeltaTotal / $quantityDeltaSamples, 2);
        }

        if ($unitCostDeltaSamples > 0) {
            $stats['average_abs_unit_cost_delta_percent'] = round($unitCostDeltaTotal / $unitCostDeltaSamples, 2);
        }

        return $stats;
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
