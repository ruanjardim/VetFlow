<?php

namespace App\Modules\Implementation\Services;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationPilotCheck;
use Illuminate\Support\Collection;

class ImplementationPilotChecklistService
{
    /** @var array<string, array{label: string, description: string}> */
    private const CHECKS = [
        'data_reviewed' => [
            'label' => 'Dados importados revisados',
            'description' => 'A equipe conferiu amostras dos cadastros e saldos migrados.',
        ],
        'quality_resolved' => [
            'label' => 'Pendências de qualidade tratadas',
            'description' => 'As pendências foram corrigidas ou aceitas conscientemente para o piloto.',
        ],
        'access_validated' => [
            'label' => 'Acessos da equipe validados',
            'description' => 'Usuários e permissões necessários ao escopo do piloto foram conferidos.',
        ],
        'backup_aligned' => [
            'label' => 'Rotina de backup alinhada',
            'description' => 'Responsáveis e procedimento de recuperação foram comunicados.',
        ],
        'training_completed' => [
            'label' => 'Treinamento operacional realizado',
            'description' => 'A equipe piloto conhece os fluxos que usará e o canal de suporte.',
        ],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::CHECKS);
    }

    /**
     * @param  Collection<int, Clinic>  $clinics
     * @return array<int, array<string, mixed>>
     */
    public function forClinics(Collection $clinics): array
    {
        if ($clinics->isEmpty()) {
            return [];
        }

        $latestChecks = ImplementationPilotCheck::query()
            ->whereIn('clinic_id', $clinics->pluck('id'))
            ->whereIn('check_key', self::keys())
            ->latest('decided_at')
            ->latest('id')
            ->get()
            ->unique(fn (ImplementationPilotCheck $check): string => $check->clinic_id.'|'.$check->check_key)
            ->groupBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use ($latestChecks): array {
                $decisions = $latestChecks->get($clinic->id, collect())->keyBy('check_key');
                $checks = collect(self::CHECKS)
                    ->map(function (array $definition, string $key) use ($decisions): array {
                        /** @var ImplementationPilotCheck|null $decision */
                        $decision = $decisions->get($key);

                        return [
                            'key' => $key,
                            'label' => $definition['label'],
                            'description' => $definition['description'],
                            'completed' => $decision?->completed ?? false,
                            'notes' => $decision?->notes,
                            'decided_at' => $decision?->decided_at,
                            'user_name' => $decision?->user_name,
                            'has_decision' => $decision !== null,
                        ];
                    })
                    ->values();
                $completed = $checks->where('completed', true)->count();
                $total = count(self::CHECKS);

                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->trade_name,
                    'completed_checks' => $completed,
                    'total_checks' => $total,
                    'percentage' => (int) round(($completed / $total) * 100),
                    'checks' => $checks->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function record(
        Clinic $clinic,
        User $user,
        string $key,
        bool $completed,
        ?string $notes
    ): ImplementationPilotCheck {
        $definition = self::CHECKS[$key];

        return ImplementationPilotCheck::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $user->name,
            'check_key' => $key,
            'check_label' => $definition['label'],
            'completed' => $completed,
            'notes' => filled($notes) ? trim((string) $notes) : null,
            'decided_at' => now(),
        ]);
    }
}
