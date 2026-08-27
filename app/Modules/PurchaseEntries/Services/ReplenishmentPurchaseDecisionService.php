<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;

class ReplenishmentPurchaseDecisionService
{
    public const VERSION = 2;

    public const ADJUSTMENT_REASONS = [
        'demand_change' => 'Demanda diferente do previsto',
        'stock_count' => 'Estoque físico divergente',
        'supplier_availability' => 'Disponibilidade do fornecedor',
        'commercial_terms' => 'Preço, prazo ou condição comercial',
        'package_size' => 'Embalagem ou lote mínimo',
        'other' => 'Outro motivo',
    ];

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
        ?string $adjustmentReason = null,
        ?string $adjustmentNote = null,
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
        $comparison = $this->comparison(
            $suggestedQuantity,
            $suggestedUnitCost,
            $suggestedSupplierId,
            $quantity,
            $unitCost,
            $actualSupplierId,
        );
        $validReason = array_key_exists((string) $adjustmentReason, self::ADJUSTMENT_REASONS)
            ? (string) $adjustmentReason
            : null;

        return array_merge($metadata, [
            'replenishment_decision' => [
                'version' => self::VERSION,
                'evidence_status' => 'valid',
                'evidence_hash' => $evidence['hash'],
                'classification' => $comparison['adjusted'] ? 'adjusted' : 'kept',
                'quantity' => [
                    'suggested' => $suggestedQuantity,
                    'actual' => $quantity,
                    'delta' => $comparison['quantity_delta'],
                    'delta_percent' => $this->percentageDelta($suggestedQuantity, $comparison['quantity_delta']),
                    'changed' => $comparison['quantity_changed'],
                ],
                'unit_cost' => [
                    'suggested' => $suggestedUnitCost,
                    'actual' => $unitCost,
                    'delta' => $comparison['unit_cost_delta'],
                    'delta_percent' => $this->percentageDelta($suggestedUnitCost, $comparison['unit_cost_delta']),
                    'changed' => $comparison['unit_cost_changed'],
                ],
                'supplier' => [
                    'suggested_id' => $suggestedSupplierId,
                    'actual_id' => $actualSupplierId,
                    'status' => $comparison['supplier_status'],
                ],
                'adjustment_reason' => $comparison['adjusted'] && $validReason !== null
                    ? [
                        'code' => $validReason,
                        'label' => self::ADJUSTMENT_REASONS[$validReason],
                        'note' => trim((string) $adjustmentNote) ?: null,
                    ]
                    : null,
                'evaluated_at' => now()->toISOString(),
            ],
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function requiresAdjustmentReason(
        int $clinicId,
        int $productId,
        ?int $supplierId,
        float $quantity,
        float $unitCost,
        array $metadata,
        ?string $intelligenceStatus,
    ): bool {
        if ($intelligenceStatus !== 'replenishment_suggestion') {
            return false;
        }

        $evidence = $metadata['evidence'] ?? null;

        if (! $this->evidence->validEnvelope($evidence)) {
            return false;
        }

        $snapshot = $evidence['snapshot'];

        if ((int) ($snapshot['clinic_id'] ?? 0) !== $clinicId
            || (int) ($snapshot['product_id'] ?? 0) !== $productId) {
            return false;
        }

        return $this->comparison(
            (float) $snapshot['suggested_quantity'],
            (float) $snapshot['unit_cost'],
            $snapshot['supplier_id'] === null ? null : (int) $snapshot['supplier_id'],
            $quantity,
            $unitCost,
            $supplierId,
        )['adjusted'];
    }

    /** @return array<string, bool|float|string> */
    private function comparison(
        float $suggestedQuantity,
        float $suggestedUnitCost,
        ?int $suggestedSupplierId,
        float $quantity,
        float $unitCost,
        ?int $actualSupplierId,
    ): array {
        $quantityDelta = round($quantity - $suggestedQuantity, 3);
        $unitCostDelta = round($unitCost - $suggestedUnitCost, 2);
        $quantityChanged = abs($quantityDelta) >= 0.0005;
        $unitCostChanged = abs($unitCostDelta) >= 0.005;
        $supplierStatus = $suggestedSupplierId === null
            ? 'unavailable'
            : ($suggestedSupplierId === $actualSupplierId ? 'kept' : 'changed');

        return [
            'quantity_delta' => $quantityDelta,
            'unit_cost_delta' => $unitCostDelta,
            'quantity_changed' => $quantityChanged,
            'unit_cost_changed' => $unitCostChanged,
            'supplier_status' => $supplierStatus,
            'adjusted' => $quantityChanged || $unitCostChanged || $supplierStatus === 'changed',
        ];
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
