<?php

namespace App\Modules\Implementation\Services;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationImport;
use Illuminate\Support\Collection;

class ImplementationReadinessService
{
    /** @var array<string, string> */
    private const BLOCKS = [
        'tutors' => 'Responsáveis',
        'patients' => 'Pacientes',
        'suppliers' => 'Fornecedores',
        'products' => 'Produtos',
        'stock' => 'Estoque inicial',
        'financial' => 'Financeiro',
    ];

    /**
     * @param  Collection<int, Clinic>  $clinics
     * @return array<int, array<string, mixed>>
     */
    public function forClinics(Collection $clinics): array
    {
        if ($clinics->isEmpty()) {
            return [];
        }

        $latestImports = ImplementationImport::query()
            ->whereIn('clinic_id', $clinics->pluck('id'))
            ->whereIn('entity_type', array_keys(self::BLOCKS))
            ->latest('completed_at')
            ->latest('id')
            ->get()
            ->unique(fn (ImplementationImport $import): string => $import->clinic_id.'|'.$import->entity_type)
            ->groupBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use ($latestImports): array {
                $importsByType = $latestImports
                    ->get($clinic->id, collect())
                    ->keyBy('entity_type');
                $blocks = collect(self::BLOCKS)
                    ->map(function (string $label, string $type) use ($importsByType): array {
                        /** @var ImplementationImport|null $import */
                        $import = $importsByType->get($type);

                        return [
                            'type' => $type,
                            'label' => $label,
                            'completed' => $import !== null,
                            'completed_at' => $import?->completed_at,
                            'imported_count' => $import?->imported_count,
                            'source' => $import?->data_source,
                        ];
                    })
                    ->values();
                $completed = $blocks->where('completed', true)->count();
                $total = count(self::BLOCKS);

                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->trade_name,
                    'completed_blocks' => $completed,
                    'total_blocks' => $total,
                    'percentage' => (int) round(($completed / $total) * 100),
                    'blocks' => $blocks->all(),
                ];
            })
            ->values()
            ->all();
    }
}
