<?php

namespace App\Modules\PurchaseEntries\Services;

use Illuminate\Support\Collection;

class SupplierProductSignalService
{
    /**
     * @param  Collection<int, array<string, mixed>>  $batches
     * @return array{profiles: array<int, array<string, mixed>>, reference: array<string, mixed>}
     */
    public function summarize(Collection $batches, ?int $referenceSupplierId): array
    {
        $profiles = $batches
            ->filter(fn (array $batch): bool => ! empty($batch['supplier_id']))
            ->groupBy('supplier_id')
            ->map(fn (Collection $supplierBatches): array => $this->profile($supplierBatches));

        return [
            'profiles' => $profiles->values()->all(),
            'reference' => $referenceSupplierId === null
                ? $this->emptyProfile()
                : $profiles->get($referenceSupplierId, $this->emptyProfile()),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $batches
     * @return array<string, mixed>
     */
    private function profile(Collection $batches): array
    {
        $latest = $batches
            ->sortByDesc(fn (array $batch) => $batch['received_at']?->getTimestamp() ?? 0)
            ->first();
        $quantity = round((float) $batches->sum('quantity'), 3);
        $totalCost = (float) $batches->sum(
            fn (array $batch): float => (float) $batch['quantity'] * (float) $batch['unit_cost']
        );
        $leadTimes = $batches
            ->pluck('lead_time_days')
            ->filter(fn ($days): bool => $days !== null)
            ->map(fn ($days): int => (int) $days)
            ->values();

        return [
            'supplier_id' => (int) $latest['supplier_id'],
            'supplier_name' => $latest['supplier_name'],
            'deliveries_count' => $batches->count(),
            'quantity_received' => $quantity,
            'average_batch_quantity' => $batches->isEmpty()
                ? 0.0
                : round($quantity / $batches->count(), 3),
            'average_unit_cost' => $quantity > 0 ? round($totalCost / $quantity, 2) : 0.0,
            'latest_unit_cost' => round((float) $latest['unit_cost'], 2),
            'last_received_at' => $latest['received_at'],
            'lead_time_samples' => $leadTimes->count(),
            'average_lead_time_days' => $leadTimes->isEmpty()
                ? null
                : (int) round((float) $leadTimes->avg()),
            'minimum_lead_time_days' => $leadTimes->isEmpty() ? null : $leadTimes->min(),
            'maximum_lead_time_days' => $leadTimes->isEmpty() ? null : $leadTimes->max(),
            'has_lead_time' => $leadTimes->isNotEmpty(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyProfile(): array
    {
        return [
            'supplier_id' => null,
            'supplier_name' => null,
            'deliveries_count' => 0,
            'quantity_received' => 0.0,
            'average_batch_quantity' => 0.0,
            'average_unit_cost' => 0.0,
            'latest_unit_cost' => 0.0,
            'last_received_at' => null,
            'lead_time_samples' => 0,
            'average_lead_time_days' => null,
            'minimum_lead_time_days' => null,
            'maximum_lead_time_days' => null,
            'has_lead_time' => false,
        ];
    }
}
