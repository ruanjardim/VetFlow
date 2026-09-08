<?php

namespace App\Modules\Implementation\Services;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationPilotDecision;
use DomainException;
use Illuminate\Support\Collection;

class ImplementationPilotReadinessService
{
    /**
     * @param  Collection<int, Clinic>  $clinics
     * @param  array<int, array<string, mixed>>  $coverage
     * @param  array<int, array<string, mixed>>  $quality
     * @param  array<int, array<string, mixed>>  $checklists
     * @param  array<int, array<string, mixed>>  $releases
     * @return array<int, array<string, mixed>>
     */
    public function forClinics(
        Collection $clinics,
        array $coverage,
        array $quality,
        array $checklists,
        array $releases
    ): array {
        if ($clinics->isEmpty()) {
            return [];
        }

        $coverageByClinic = collect($coverage)->keyBy('clinic_id');
        $qualityByClinic = collect($quality)->keyBy('clinic_id');
        $checklistsByClinic = collect($checklists)->keyBy('clinic_id');
        $releasesByClinic = collect($releases)->keyBy('clinic_id');
        $latestDecisions = ImplementationPilotDecision::query()
            ->whereIn('clinic_id', $clinics->pluck('id'))
            ->latest('decided_at')
            ->latest('id')
            ->get()
            ->unique('clinic_id')
            ->keyBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use (
                $coverageByClinic,
                $qualityByClinic,
                $checklistsByClinic,
                $releasesByClinic,
                $latestDecisions
            ): array {
                $clinicCoverage = $coverageByClinic->get($clinic->id, []);
                $clinicQuality = $qualityByClinic->get($clinic->id, []);
                $clinicChecklist = $checklistsByClinic->get($clinic->id, []);
                $clinicRelease = $releasesByClinic->get($clinic->id, []);
                $snapshot = $this->snapshot(
                    $clinicCoverage,
                    $clinicQuality,
                    $clinicChecklist,
                    $clinicRelease
                );
                $hash = $this->hash($snapshot);
                $gates = $this->gates($snapshot);
                $gatesPassed = collect($gates)->every('passed');
                /** @var ImplementationPilotDecision|null $decision */
                $decision = $latestDecisions->get($clinic->id);
                $decisionCurrent = $decision !== null
                    && hash_equals($hash, $decision->evidence_hash);
                $status = $this->status($gatesPassed, $decision, $decisionCurrent);

                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->trade_name,
                    'gates' => $gates,
                    'gates_passed' => $gatesPassed,
                    'evidence_snapshot' => $snapshot,
                    'evidence_hash' => $hash,
                    'status' => $status,
                    'decision' => $decision?->decision,
                    'decision_current' => $decisionCurrent,
                    'decision_notes' => $decision?->notes,
                    'decided_at' => $decision?->decided_at,
                    'decision_user_name' => $decision?->user_name,
                ];
            })
            ->values()
            ->all();
    }

    public function record(
        Clinic $clinic,
        User $user,
        string $decision,
        ?string $notes,
        array $readiness
    ): ImplementationPilotDecision {
        if ($decision === 'approved' && ! $readiness['gates_passed']) {
            throw new DomainException(
                'A aprovação exige que os quatro portões de prontidão estejam atendidos.'
            );
        }

        return ImplementationPilotDecision::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $user->name,
            'decision' => $decision,
            'evidence_snapshot' => $readiness['evidence_snapshot'],
            'evidence_hash' => $readiness['evidence_hash'],
            'notes' => filled($notes) ? trim((string) $notes) : null,
            'decided_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(
        array $coverage,
        array $quality,
        array $checklist,
        array $release
    ): array {
        return [
            'coverage' => [
                'completed' => (int) ($coverage['completed_blocks'] ?? 0),
                'total' => (int) ($coverage['total_blocks'] ?? 6),
            ],
            'quality' => [
                'evaluated' => (int) ($quality['evaluated_blocks'] ?? 0),
                'ready' => (int) ($quality['ready_blocks'] ?? 0),
                'issues' => (int) ($quality['total_issues'] ?? 0),
            ],
            'checklist' => [
                'completed' => (int) ($checklist['completed_checks'] ?? 0),
                'total' => (int) ($checklist['total_checks'] ?? 5),
            ],
            'release' => [
                'revision' => isset($release['revision'])
                    ? (int) $release['revision']
                    : null,
            ],
        ];
    }

    /** @return array<int, array{key: string, label: string, passed: bool, summary: string}> */
    private function gates(array $snapshot): array
    {
        $coverage = $snapshot['coverage'];
        $quality = $snapshot['quality'];
        $checklist = $snapshot['checklist'];
        $release = $snapshot['release'];
        $coveragePassed = $coverage['total'] > 0
            && $coverage['completed'] === $coverage['total'];
        $qualityPassed = $quality['evaluated'] === $coverage['total']
            && $quality['ready'] === $coverage['total']
            && $quality['issues'] === 0;
        $checklistPassed = $checklist['total'] > 0
            && $checklist['completed'] === $checklist['total'];
        $releasePassed = $release['revision'] !== null;

        return [
            [
                'key' => 'coverage',
                'label' => 'Importações concluídas',
                'passed' => $coveragePassed,
                'summary' => "{$coverage['completed']} de {$coverage['total']} blocos",
            ],
            [
                'key' => 'quality',
                'label' => 'Qualidade revisada',
                'passed' => $qualityPassed,
                'summary' => "{$quality['ready']} de {$quality['evaluated']} sem pendências",
            ],
            [
                'key' => 'checklist',
                'label' => 'Checklist do piloto',
                'passed' => $checklistPassed,
                'summary' => "{$checklist['completed']} de {$checklist['total']} itens",
            ],
            [
                'key' => 'release',
                'label' => 'Plano da liberação',
                'passed' => $releasePassed,
                'summary' => $releasePassed
                    ? "Revisão {$release['revision']} registrada"
                    : 'Sem revisão registrada',
            ],
        ];
    }

    /** @return array{key: string, label: string, description: string} */
    private function status(
        bool $gatesPassed,
        ?ImplementationPilotDecision $decision,
        bool $decisionCurrent
    ): array {
        if (! $gatesPassed) {
            return [
                'key' => 'blocked',
                'label' => 'Evidências pendentes',
                'description' => 'Conclua os portões pendentes antes de aprovar o piloto.',
            ];
        }

        if (! $decisionCurrent) {
            return [
                'key' => 'awaiting',
                'label' => 'Aguardando decisão',
                'description' => $decision === null
                    ? 'Os portões estão atendidos; registre a decisão humana.'
                    : 'As evidências mudaram; registre uma nova decisão.',
            ];
        }

        return $decision->decision === 'approved'
            ? [
                'key' => 'approved',
                'label' => 'Piloto aprovado',
                'description' => 'A decisão corresponde às evidências atuais.',
            ]
            : [
                'key' => 'held',
                'label' => 'Piloto em espera',
                'description' => 'Os portões atendem, mas a decisão humana mantém o piloto em espera.',
            ];
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
