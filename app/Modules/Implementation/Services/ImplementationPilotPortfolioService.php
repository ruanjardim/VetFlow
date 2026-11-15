<?php

namespace App\Modules\Implementation\Services;

class ImplementationPilotPortfolioService
{
    /** @var array<string, string> */
    private const FILTERS = [
        'blocked' => 'Evidências pendentes',
        'awaiting' => 'Aguardando decisão',
        'held' => 'Piloto em espera',
        'approved' => 'Piloto aprovado',
    ];

    /** @var array<string, int> */
    private const PRIORITY = [
        'blocked' => 1,
        'awaiting' => 2,
        'held' => 3,
        'approved' => 4,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $readiness
     * @return array<string, mixed>
     */
    public function summarize(array $readiness, ?string $status = null): array
    {
        $items = collect($readiness);
        $selectedStatus = isset(self::FILTERS[$status ?? '']) ? $status : null;
        $counts = collect(self::FILTERS)
            ->mapWithKeys(fn (string $label, string $key): array => [
                $key => $items->where('status.key', $key)->count(),
            ])
            ->all();
        $visible = $selectedStatus === null
            ? $items
            : $items->where('status.key', $selectedStatus);
        $visible = $visible
            ->sortBy(fn (array $item): string => sprintf(
                '%d|%s',
                self::PRIORITY[$item['status']['key']] ?? 99,
                mb_strtolower((string) $item['clinic_name'])
            ))
            ->values();

        return [
            'total' => $items->count(),
            'visible' => $visible->count(),
            'stale_decisions' => $items
                ->filter(fn (array $item): bool => $item['decision'] !== null
                    && ! $item['decision_current'])
                ->count(),
            'counts' => $counts,
            'filters' => self::FILTERS,
            'selected_status' => $selectedStatus,
            'items' => $visible->all(),
        ];
    }
}
