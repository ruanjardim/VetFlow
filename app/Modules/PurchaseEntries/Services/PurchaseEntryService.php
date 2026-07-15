<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\Financial\Models\FinancialTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseEntryService
{
    public function __construct(private readonly InventoryMovementService $inventoryMovementService)
    {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PurchaseEntry::query()
            ->with(['supplier', 'financialTransactions'])
            ->withCount('items')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): PurchaseEntry
    {
        return PurchaseEntry::query()
            ->with(['supplier', 'items.product', 'financialTransactions'])
            ->findOrFail($id);
    }

    public function create(array $data): PurchaseEntry
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            $financialData = $this->extractFinancialData($data);
            unset(
                $data['items'],
                $data['payment_due_date'],
                $data['payment_status'],
                $data['payment_method'],
                $data['payment_reference'],
                $data['installments_count'],
                $data['installment_interval_days'],
                $data['paid_at']
            );

            $data['code'] = $this->nextCode();
            $data['purchased_at'] = $data['purchased_at'] ?? now();

            if (($data['status'] ?? 'received') === 'received') {
                $data['received_at'] = $data['received_at'] ?? now();
            } else {
                $data['received_at'] = null;
            }

            $entry = PurchaseEntry::query()->create($data);
            $this->syncItems($entry, $items);
            $this->recalculateTotals($entry);
            $this->applyInventoryIfReceived($entry->refresh());
            $this->syncPayable($entry->refresh(), $financialData);

            return $entry->refresh()->load(['supplier', 'items.product', 'financialTransactions']);
        });
    }

    public function update(int $id, array $data): PurchaseEntry
    {
        return DB::transaction(function () use ($id, $data) {
            $items = $data['items'] ?? [];
            $financialData = $this->extractFinancialData($data);
            unset(
                $data['items'],
                $data['payment_due_date'],
                $data['payment_status'],
                $data['payment_method'],
                $data['payment_reference'],
                $data['installments_count'],
                $data['installment_interval_days'],
                $data['paid_at']
            );

            $entry = $this->findOrFail($id);

            $this->releaseInventory($entry);

            if (($data['status'] ?? $entry->status) === 'received') {
                $data['received_at'] = $data['received_at'] ?? $entry->received_at ?? now();
            } else {
                $data['received_at'] = null;
            }

            $entry->update($data);
            $entry->items()->delete();

            $this->syncItems($entry->refresh(), $items);
            $this->recalculateTotals($entry->refresh());
            $this->applyInventoryIfReceived($entry->refresh());
            $this->syncPayable($entry->refresh(), $financialData);

            return $entry->refresh()->load(['supplier', 'items.product', 'financialTransactions']);
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $entry = $this->findOrFail($id);

            $this->releaseInventory($entry);
            $this->releasePayable($entry);
            $entry->items()->delete();

            return (bool) $entry->delete();
        });
    }

    private function syncItems(PurchaseEntry $entry, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeItem($item);

            if ($normalized === null) {
                continue;
            }

            $entry->items()->create($normalized);
        }
    }

    private function normalizeItem(array $item): ?array
    {
        $productId = $item['product_id'] ?? null;
        $quantity = (float) ($item['quantity'] ?? 0);

        if (! $productId || $quantity <= 0) {
            return null;
        }

        $product = Product::query()->find($productId);

        if (! $product) {
            return null;
        }

        $unitCost = $item['unit_cost'] ?? null;
        $unitCost = $unitCost !== null && $unitCost !== ''
            ? (float) $unitCost
            : (float) $product->cost_price;
        $salePrice = $item['sale_price'] ?? null;
        $salePrice = $salePrice !== null && $salePrice !== ''
            ? (float) $salePrice
            : (float) $product->sale_price;
        $marginPercent = $item['margin_percent'] ?? null;
        $marginPercent = $marginPercent !== null && $marginPercent !== ''
            ? (float) $marginPercent
            : $this->marginPercent($unitCost, $salePrice);
        $minimumStock = $item['minimum_stock_after_entry'] ?? null;
        $minimumStock = $minimumStock !== null && $minimumStock !== ''
            ? (float) $minimumStock
            : null;
        $metadata = $this->decodeMetadata($item['intelligence_metadata'] ?? null);

        return [
            'product_id' => $product->id,
            'description' => trim((string) ($item['description'] ?? '')) ?: $product->name,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'barcode_snapshot' => trim((string) ($item['barcode_snapshot'] ?? '')) ?: ($product->gtin ?: $product->barcode),
            'supplier_sku' => trim((string) ($item['supplier_sku'] ?? '')) ?: null,
            'sale_price' => $salePrice,
            'margin_percent' => $marginPercent,
            'update_sale_price' => (bool) ($item['update_sale_price'] ?? false),
            'minimum_stock_after_entry' => $minimumStock,
            'intelligence_status' => trim((string) ($item['intelligence_status'] ?? '')) ?: null,
            'intelligence_metadata' => array_merge($metadata, [
                'normalized_at' => now()->toDateTimeString(),
                'product_cost_before_entry' => (float) $product->cost_price,
                'product_sale_price_before_entry' => (float) $product->sale_price,
                'product_stock_before_entry' => (float) $product->stock_quantity,
            ]),
            'lot_number' => trim((string) ($item['lot_number'] ?? '')) ?: null,
            'expires_at' => $item['expires_at'] ?? null,
            'notes' => $item['notes'] ?? null,
        ];
    }

    private function recalculateTotals(PurchaseEntry $entry): void
    {
        $entry->load('items');

        $subtotal = round((float) $entry->items->sum('total_cost'), 2);

        $entry->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);
    }

    private function applyInventoryIfReceived(PurchaseEntry $entry): void
    {
        if ($entry->status !== 'received') {
            return;
        }

        $entry->loadMissing(['supplier', 'items.product']);

        foreach ($entry->items as $item) {
            if ($item->inventory_movement_id) {
                continue;
            }

            $movement = $this->inventoryMovementService->create([
                'clinic_id' => $entry->clinic_id,
                'product_id' => $item->product_id,
                'purchase_entry_id' => $entry->id,
                'purchase_entry_item_id' => $item->id,
                'type' => 'entry',
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'lot_number' => $item->lot_number,
                'expires_at' => $item->expires_at,
                'occurred_at' => $entry->received_at ?? $entry->purchased_at ?? now(),
                'reason' => 'Entrada de mercadoria '.$entry->code,
                'source' => 'purchase_entry',
                'notes' => $this->movementNotes($entry, $item->description),
                'metadata' => [
                    'purchase_entry_code' => $entry->code,
                    'purchase_entry_id' => $entry->id,
                    'purchase_entry_item_id' => $item->id,
                    'invoice_number' => $entry->invoice_number,
                    'invoice_key' => $entry->invoice_key,
                    'supplier_id' => $entry->supplier_id,
                    'supplier_name' => $entry->supplier?->name,
                    'item_description' => $item->description,
                ],
            ]);

            $item->update(['inventory_movement_id' => $movement->id]);

            $updates = [];

            if ((float) $item->unit_cost > 0) {
                $updates['cost_price'] = $item->unit_cost;
            }

            if ($item->update_sale_price && (float) $item->sale_price > 0) {
                $updates['sale_price'] = $item->sale_price;
            }

            if ($item->minimum_stock_after_entry !== null) {
                $updates['minimum_stock'] = $item->minimum_stock_after_entry;
            }

            if ($updates !== []) {
                Product::query()
                    ->whereKey($item->product_id)
                    ->update($updates);
            }
        }
    }

    private function syncPayable(PurchaseEntry $entry, array $financialData): void
    {
        if ($entry->status !== 'received' || (float) $entry->total <= 0) {
            $this->releasePayable($entry);
            return;
        }

        $entry->loadMissing('supplier');

        $status = $financialData['payment_status'] ?: 'pending';
        $installmentTotal = max(1, (int) ($financialData['installments_count'] ?: 1));
        $intervalDays = max(1, (int) ($financialData['installment_interval_days'] ?: 30));
        $firstDueDate = $financialData['payment_due_date']
            ?: optional($entry->purchased_at)->toDateString()
            ?: today()->toDateString();

        $this->releasePayable($entry);

        foreach ($this->installmentAmounts((float) $entry->total, $installmentTotal) as $index => $amount) {
            $installmentNumber = $index + 1;
            $paidAt = $status === 'paid'
                ? ($financialData['paid_at'] ?: $entry->received_at ?: now())
                : null;

            FinancialTransaction::query()->create([
                'clinic_id' => $entry->clinic_id,
                'supplier_id' => $entry->supplier_id,
                'purchase_entry_id' => $entry->id,
                'installment_number' => $installmentNumber,
                'installment_total' => $installmentTotal,
                'type' => 'expense',
                'description' => $this->payableDescription($entry, $installmentNumber, $installmentTotal),
                'amount' => $amount,
                'due_date' => Carbon::parse($firstDueDate)->addDays($intervalDays * $index)->toDateString(),
                'paid_at' => $paidAt,
                'status' => $status,
                'payment_method' => $financialData['payment_method'] ?: null,
                'reference' => $this->payableReference($entry, $financialData, $installmentNumber, $installmentTotal),
                'notes' => $this->payableNotes($entry),
            ]);
        }
    }

    private function releasePayable(PurchaseEntry $entry): void
    {
        $transactions = FinancialTransaction::query()
            ->where('purchase_entry_id', $entry->id)
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->delete();
        }
    }

    private function releaseInventory(PurchaseEntry $entry): void
    {
        $entry->loadMissing('items');

        $movementIds = $entry->items
            ->pluck('inventory_movement_id')
            ->filter()
            ->merge(
                InventoryMovement::query()
                    ->where('source', 'purchase_entry')
                    ->where('purchase_entry_id', $entry->id)
                    ->pluck('id')
            )
            ->unique()
            ->values();

        foreach ($movementIds as $movementId) {
            $movement = InventoryMovement::withTrashed()->find($movementId);

            if ($movement && ! $movement->trashed()) {
                $this->inventoryMovementService->delete($movement->id);
            }
        }

        $entry->items()->update(['inventory_movement_id' => null]);
    }

    private function movementNotes(PurchaseEntry $entry, ?string $description): string
    {
        $parts = array_filter([
            $entry->supplier?->name ? 'Fornecedor: '.$entry->supplier->name : null,
            $entry->invoice_number ? 'NF: '.$entry->invoice_number : null,
            $description,
        ]);

        return implode(' | ', $parts);
    }

    private function payableNotes(PurchaseEntry $entry): string
    {
        $parts = array_filter([
            $entry->supplier?->name ? 'Fornecedor: '.$entry->supplier->name : null,
            $entry->invoice_number ? 'NF: '.$entry->invoice_number : null,
            $entry->notes,
        ]);

        return implode(' | ', $parts);
    }

    private function payableDescription(PurchaseEntry $entry, int $installmentNumber, int $installmentTotal): string
    {
        $description = 'Compra '.$entry->code;

        if ($installmentTotal > 1) {
            $description .= ' Parcela '.$installmentNumber.'/'.$installmentTotal;
        }

        return $description;
    }

    private function payableReference(PurchaseEntry $entry, array $financialData, int $installmentNumber, int $installmentTotal): ?string
    {
        $reference = $financialData['payment_reference'] ?: $entry->invoice_number;

        if (! $reference || $installmentTotal <= 1) {
            return $reference;
        }

        return $reference.' - Parcela '.$installmentNumber.'/'.$installmentTotal;
    }

    private function installmentAmounts(float $total, int $installmentTotal): array
    {
        $totalCents = (int) round($total * 100);
        $baseCents = intdiv($totalCents, $installmentTotal);
        $remainder = $totalCents % $installmentTotal;
        $amounts = [];

        for ($index = 0; $index < $installmentTotal; $index++) {
            $cents = $baseCents + ($index < $remainder ? 1 : 0);
            $amounts[] = $cents / 100;
        }

        return $amounts;
    }

    private function marginPercent(float $unitCost, float $salePrice): ?float
    {
        if ($salePrice <= 0) {
            return null;
        }

        return round((($salePrice - $unitCost) / $salePrice) * 100, 2);
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractFinancialData(array $data): array
    {
        return [
            'payment_due_date' => $data['payment_due_date'] ?? null,
            'payment_status' => $data['payment_status'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'installments_count' => $data['installments_count'] ?? 1,
            'installment_interval_days' => $data['installment_interval_days'] ?? 30,
            'paid_at' => $data['paid_at'] ?? null,
        ];
    }

    private function nextCode(): string
    {
        $nextId = ((int) PurchaseEntry::withTrashed()->max('id')) + 1;

        return 'ENT-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}
