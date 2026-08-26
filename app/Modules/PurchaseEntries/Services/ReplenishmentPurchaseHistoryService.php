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
    ): LengthAwarePaginator {
        $query = PurchaseEntryItem::query()
            ->where('intelligence_status', 'replenishment_suggestion')
            ->whereHas('purchaseEntry', fn (Builder $entryQuery) => $entryQuery
                ->when(
                    $user->clinic_id !== null,
                    fn (Builder $clinicQuery) => $clinicQuery->where('clinic_id', $user->clinic_id),
                ))
            ->with([
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
}
