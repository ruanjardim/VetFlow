<?php

namespace App\Modules\Sales\Requests\Concerns;

/**
 * Input normalization shared by the PDV sale and quote requests: Brazilian
 * decimal strings ("1.234,56") and manual cart lines without a catalog item.
 */
trait NormalizesSaleInput
{
    /**
     * @param array<int|string, mixed> $items
     * @return array<int|string, mixed>
     */
    protected function normalizeItemsInput(array $items, bool $pdvCheckout): array
    {
        return array_map(function ($item) use ($pdvCheckout) {
            if (! is_array($item)) {
                return $item;
            }

            $item['quantity'] = $this->normalizeDecimalValue($item['quantity'] ?? null);
            $item['unit_price'] = $this->normalizeDecimalValue($item['unit_price'] ?? null);
            $item['original_unit_price'] = $this->normalizeDecimalValue($item['original_unit_price'] ?? null);
            $item['discount_total'] = $this->normalizeDecimalValue($item['discount_total'] ?? null);

            if ($pdvCheckout && $item['unit_price'] !== null && $item['unit_price'] !== '') {
                $item['original_unit_price'] = $item['unit_price'];
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $description = trim((string) ($item['description'] ?? ''));
            $hasCatalogItem = ! empty($item['product_id']) || ! empty($item['petshop_service_id']);
            $hasManualEntry = $description !== '' || $unitPrice > 0;

            if (! $hasCatalogItem && $hasManualEntry) {
                $item['type'] = 'custom';
                $item['product_id'] = null;
                $item['petshop_service_id'] = null;
            }

            if ($unitPrice > 0 && $quantity <= 0) {
                $item['quantity'] = '1';
            }

            if (($item['type'] ?? '') === 'custom' && $description === '' && $unitPrice > 0) {
                $item['description'] = 'Item avulso';
            }

            return $item;
        }, $items);
    }

    protected function normalizeDecimalValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return $value;
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }
}
