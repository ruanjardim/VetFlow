<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;

class ReplenishmentPurchaseDecisionService
{
    public const VERSION = 1;

    public function __construct(
        private readonly ReplenishmentEvidenceService $evidence,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(
        PurchaseEntry $entry,
        Product $product,
        float $quantity,
        float $unitCost,
        array $metadata,
        ?string $intelligenceStatus,
    ): array {
        if ($intelligenceStatus !== 'replenishment_suggestion') {
            return $metadata;
        }

        $evidence = $metadata['evidence'] ?? null;

        if (! $this->evidence->validEnvelope($evidence)) {
            return $this->withUnavailableDecision($metadata, 'invalid');
        }

        $snapshot = $evidence['snapshot'];

        if ((int) ($snapshot['clinic_id'] ?? 0) !== (int) $entry->clinic_id
            || (int) ($snapshot['product_id'] ?? 0) !== (int) $product->id) {
            return $this->withUnavailableDecision($metadata, 'scope_mismatch');
        }

        $suggestedQuantity = (float) $snapshot['suggested_quantity'];
        $suggestedUnitCost = (float) $snapshot['unit_cost'];
        $suggestedSupplierId = $snapshot['supplier_id'] === null
            ? null
            : (int) $snapshot['supplier_id'];
        $actualSupplierId = $entry->supplier_id === null ? null : (int) $entry->supplier_id;
        $quantityDelta = round($quantity - $suggestedQuantity, 3);
        $unitCostDelta = round($unitCost - $suggestedUnitCost, 2);
        $quantityChanged = abs($quantityDelta) >= 0.0005;
        $unitCostChanged = abs($unitCostDelta) >= 0.005;
        $supplierStatus = $suggestedSupplierId === null
            ? 'unavailable'
            : ($suggestedSupplierId === $actualSupplierId ? 'kept' : 'changed');
        $adjusted = $quantityChanged || $unitCostChanged || $supplierStatus === 'changed';

        return array_merge($metadata, [
            'replenishment_decision' => [
                'version' => self::VERSION,
                'evidence_status' => 'valid',
                'evidence_hash' => $evidence['hash'],
                'classification' => $adjusted ? 'adjusted' : 'kept',
                'quantity' => [
                    'suggested' => $suggestedQuantity,
                    'actual' => $quantity,
                    'delta' => $quantityDelta,
                    'delta_percent' => $this->percentageDelta($suggestedQuantity, $quantityDelta),
                    'changed' => $quantityChanged,
                ],
                'unit_cost' => [
                    'suggested' => $suggestedUnitCost,
                    'actual' => $unitCost,
                    'delta' => $unitCostDelta,
                    'delta_percent' => $this->percentageDelta($suggestedUnitCost, $unitCostDelta),
                    'changed' => $unitCostChanged,
                ],
                'supplier' => [
                    'suggested_id' => $suggestedSupplierId,
                    'actual_id' => $actualSupplierId,
                    'status' => $supplierStatus,
                ],
                'evaluated_at' => now()->toISOString(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function withUnavailableDecision(array $metadata, string $evidenceStatus): array
    {
        return array_merge($metadata, [
            'replenishment_decision' => [
                'version' => self::VERSION,
                'evidence_status' => $evidenceStatus,
                'classification' => 'unavailable',
                'evaluated_at' => now()->toISOString(),
            ],
        ]);
    }

    private function percentageDelta(float $baseline, float $delta): ?float
    {
        if (abs($baseline) < 0.0000001) {
            return null;
        }

        return round(($delta / $baseline) * 100, 2);
    }
}
