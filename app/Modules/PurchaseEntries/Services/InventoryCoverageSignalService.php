<?php

namespace App\Modules\PurchaseEntries\Services;

class InventoryCoverageSignalService
{
    /**
     * @param  array<string, mixed>  $demand
     * @param  array<string, mixed>  $supplier
     * @return array<string, mixed>
     */
    public function calculate(float $stock, array $demand, array $supplier): array
    {
        $windowDays = max(1, (int) $demand['window_days']);
        $netDemand = max(0, (float) $demand['net_quantity']);
        $dailyDemand = $netDemand / $windowDays;
        $availableStock = max(0, $stock);
        $coverageDays = $dailyDemand > 0
            ? round($availableStock / $dailyDemand, 1)
            : null;
        $leadTimeDays = $supplier['has_lead_time']
            ? (int) $supplier['average_lead_time_days']
            : null;
        $coverageMarginDays = $coverageDays !== null && $leadTimeDays !== null
            ? round($coverageDays - $leadTimeDays, 1)
            : null;
        $projectedStockAtReceipt = $leadTimeDays !== null && $dailyDemand > 0
            ? round(max(0, $availableStock - ($dailyDemand * $leadTimeDays)), 3)
            : null;
        $status = $this->status($stock, $dailyDemand, $coverageDays, $leadTimeDays);

        return [
            'daily_demand_quantity' => round($dailyDemand, 4),
            'coverage_days' => $coverageDays,
            'lead_time_days' => $leadTimeDays,
            'coverage_margin_days' => $coverageMarginDays,
            'projected_stock_at_receipt' => $projectedStockAtReceipt,
            'risk_key' => $status['key'],
            'risk_label' => $status['label'],
            'risk_tone' => $status['tone'],
            'risk_rank' => $status['rank'],
            'summary' => $this->summary($status['key'], $coverageDays, $leadTimeDays, $coverageMarginDays),
        ];
    }

    /** @return array{key: string, label: string, tone: string, rank: int} */
    private function status(float $stock, float $dailyDemand, ?float $coverageDays, ?int $leadTimeDays): array
    {
        if ($stock <= 0) {
            return ['key' => 'critical', 'label' => 'Sem cobertura', 'tone' => 'danger', 'rank' => 0];
        }

        if ($dailyDemand <= 0 || $coverageDays === null) {
            return ['key' => 'insufficient', 'label' => 'Base insuficiente', 'tone' => 'muted-badge', 'rank' => 4];
        }

        if ($leadTimeDays === null) {
            return ['key' => 'unmeasured', 'label' => 'Prazo não observado', 'tone' => 'warning', 'rank' => 2];
        }

        if ($coverageDays <= $leadTimeDays) {
            return ['key' => 'risk', 'label' => 'Risco de ruptura', 'tone' => 'danger', 'rank' => 1];
        }

        return ['key' => 'covered', 'label' => 'Cobertura acima do prazo', 'tone' => 'success', 'rank' => 3];
    }

    private function summary(
        string $riskKey,
        ?float $coverageDays,
        ?int $leadTimeDays,
        ?float $coverageMarginDays,
    ): string {
        return match ($riskKey) {
            'critical' => 'O saldo atual não oferece cobertura; reponha somente após revisar a demanda e o fornecedor.',
            'insufficient' => 'Não há demanda líquida recente suficiente para projetar dias de cobertura.',
            'unmeasured' => 'A cobertura estimada é de '.$this->days($coverageDays).', mas não há prazo médio observado para comparação.',
            'risk' => 'A cobertura estimada de '.$this->days($coverageDays).' é menor ou igual ao prazo médio observado de '.$this->days($leadTimeDays).'.',
            default => 'A cobertura estimada de '.$this->days($coverageDays).' supera o prazo médio observado em '.$this->days($coverageMarginDays).'.',
        };
    }

    private function days(float|int|null $days): string
    {
        return number_format((float) $days, 1, ',', '.').' dia(s)';
    }
}
