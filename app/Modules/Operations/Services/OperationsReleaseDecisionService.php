<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OperationsReleaseDecision;
use App\Support\Operations\OperationalEvidenceService;
use App\Support\Operations\ReleaseIdentityService;
use App\Support\Operations\ReleaseReadinessService;
use DomainException;

class OperationsReleaseDecisionService
{
    public function __construct(
        private readonly ReleaseIdentityService $releaseIdentity,
        private readonly ReleaseReadinessService $readiness,
        private readonly OperationalEvidenceService $evidence,
        private readonly OperationsSmokeChecklistService $smokeChecklist,
    ) {}

    /** @return array<string, mixed> */
    public function state(User $user): array
    {
        $release = $this->releaseIdentity->payload();
        $evidence = $this->evidence->latest();
        $readiness = $this->readiness->evaluate(
            backupEvidencePath: $evidence['backup']['path'],
            runtimeEvidencePath: $evidence['runtime']['path'],
        );
        $smoke = $this->smokeChecklist->summary($user);
        $gates = $this->gates($release['release']['sha'], $readiness, $smoke);
        $gatesPassed = collect($gates)->every('passed');
        $publicEvidence = [
            'backup' => collect($evidence['backup'])->except('path')->all(),
            'runtime' => collect($evidence['runtime'])->except('path')->all(),
        ];
        $snapshot = $this->snapshot($release['release']['sha'], $readiness, $publicEvidence, $smoke);
        $hash = $this->hash($snapshot);
        $decision = $this->latestDecision($user, $release['release']['sha']);
        $decisionCurrent = $decision !== null && hash_equals($hash, $decision->evidence_hash);

        return [
            'release' => $release['release'],
            'release_available' => $release['status'] === 'ok',
            'environment' => app()->environment(),
            'queue_mode' => (string) config('operations.queue.mode', 'worker'),
            'queue_connection' => (string) config('queue.default'),
            'storage_disk' => (string) config('filesystems.default'),
            'readiness' => $readiness,
            'evidence' => $publicEvidence,
            'smoke_checklist' => $smoke,
            'gates' => $gates,
            'gates_passed' => $gatesPassed,
            'evidence_snapshot' => $snapshot,
            'evidence_hash' => $hash,
            'status' => $this->status($gatesPassed, $decision, $decisionCurrent),
            'decision' => $decision === null ? null : [
                'decision' => $decision->decision,
                'note' => $decision->note,
                'actor' => $decision->actor?->name,
                'decided_at' => $decision->decided_at,
                'current' => $decisionCurrent,
            ],
        ];
    }

    public function record(User $user, string $decision, ?string $note): OperationsReleaseDecision
    {
        $state = $this->state($user);

        if (! $state['release_available']) {
            throw new DomainException('Identifique o commit publicado antes de registrar a decisão.');
        }

        if ($decision === 'approved' && ! $state['gates_passed']) {
            throw new DomainException('A aprovação exige que todos os portões operacionais estejam atendidos.');
        }

        return OperationsReleaseDecision::query()->create([
            'clinic_id' => $user->clinic_id,
            'actor_user_id' => $user->id,
            'environment' => $state['environment'],
            'release_sha' => $state['release']['sha'],
            'decision' => $decision,
            'evidence_snapshot' => $state['evidence_snapshot'],
            'evidence_hash' => $state['evidence_hash'],
            'note' => filled($note) ? trim((string) $note) : null,
            'decided_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function report(User $user): array
    {
        $state = $this->state($user);

        return [
            'version' => 1,
            'generated_at' => now()->utc()->toIso8601String(),
            'environment' => $state['environment'],
            'release' => $state['release'],
            'status' => $state['status'],
            'gates' => $state['gates'],
            'technical_checks' => $state['readiness']['checks'],
            'evidence' => $state['evidence'],
            'smoke_checklist' => [
                'completed' => $state['smoke_checklist']['completed'],
                'total' => $state['smoke_checklist']['total'],
                'items' => collect($state['smoke_checklist']['items'])
                    ->map(fn (array $item): array => [
                        'key' => $item['key'],
                        'label' => $item['label'],
                        'completed' => $item['completed'],
                        'actor' => $item['actor'],
                        'decided_at' => $item['decided_at']?->toIso8601String(),
                    ])->all(),
            ],
            'decision' => $state['decision'] === null ? null : [
                'decision' => $state['decision']['decision'],
                'note' => $state['decision']['note'],
                'actor' => $state['decision']['actor'],
                'decided_at' => $state['decision']['decided_at']?->toIso8601String(),
                'current' => $state['decision']['current'],
            ],
        ];
    }

    /** @return array<int, array{key: string, label: string, passed: bool, summary: string}> */
    private function gates(?string $sha, array $readiness, array $smoke): array
    {
        $checks = collect($readiness['checks'])->keyBy('check');
        $platformPassed = $checks
            ->except(['Identidade da release', 'Probe operacional', 'Backup restauravel'])
            ->every('passed');
        $runtimePassed = (bool) ($checks->get('Probe operacional')['passed'] ?? false);
        $backupPassed = (bool) ($checks->get('Backup restauravel')['passed'] ?? false);
        $smokePassed = $smoke['total'] > 0 && $smoke['completed'] === $smoke['total'];

        return [
            [
                'key' => 'identity',
                'label' => 'Release identificada',
                'passed' => $sha !== null,
                'summary' => $sha === null ? 'SHA completo indisponível' : 'Commit '.substr($sha, 0, 7),
            ],
            [
                'key' => 'platform',
                'label' => 'Plataforma validada',
                'passed' => $platformPassed,
                'summary' => $platformPassed ? 'Configuração, banco, fila e storage aprovados' : 'Há verificações técnicas pendentes',
            ],
            [
                'key' => 'runtime',
                'label' => 'Probe operacional',
                'passed' => $runtimePassed,
                'summary' => (string) ($checks->get('Probe operacional')['detail'] ?? 'Sem avaliação'),
            ],
            [
                'key' => 'backup',
                'label' => 'Backup restaurável',
                'passed' => $backupPassed,
                'summary' => (string) ($checks->get('Backup restauravel')['detail'] ?? 'Sem avaliação'),
            ],
            [
                'key' => 'smoke',
                'label' => 'Smoke test concluído',
                'passed' => $smokePassed,
                'summary' => "{$smoke['completed']} de {$smoke['total']} itens",
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(?string $sha, array $readiness, array $evidence, array $smoke): array
    {
        return [
            'version' => 1,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'technical' => collect($readiness['checks'])
                ->mapWithKeys(fn (array $check): array => [$check['check'] => $check['passed']])
                ->all(),
            'evidence' => [
                'backup' => [
                    'identifier' => $evidence['backup']['identifier'],
                    'verified_at' => $evidence['backup']['verified_at'],
                    'status' => $evidence['backup']['status'],
                ],
                'runtime' => [
                    'identifier' => $evidence['runtime']['identifier'],
                    'verified_at' => $evidence['runtime']['verified_at'],
                    'status' => $evidence['runtime']['status'],
                ],
            ],
            'smoke' => collect($smoke['items'])
                ->mapWithKeys(fn (array $item): array => [$item['key'] => $item['completed']])
                ->all(),
        ];
    }

    private function latestDecision(User $user, ?string $sha): ?OperationsReleaseDecision
    {
        if ($sha === null) {
            return null;
        }

        return OperationsReleaseDecision::query()
            ->with('actor:id,name')
            ->where('environment', app()->environment())
            ->where('release_sha', $sha)
            ->where(function ($query) use ($user): void {
                $user->clinic_id === null
                    ? $query->whereNull('clinic_id')
                    : $query->where('clinic_id', $user->clinic_id);
            })
            ->latest('decided_at')
            ->latest('id')
            ->first();
    }

    /** @return array{key: string, label: string, description: string} */
    private function status(
        bool $gatesPassed,
        ?OperationsReleaseDecision $decision,
        bool $decisionCurrent,
    ): array {
        if (! $gatesPassed) {
            return [
                'key' => 'blocked',
                'label' => 'Liberação bloqueada',
                'description' => $decision !== null && ! $decisionCurrent
                    ? 'As evidências mudaram e ainda existem portões pendentes.'
                    : 'Conclua os portões operacionais antes de aprovar a release.',
            ];
        }

        if (! $decisionCurrent) {
            return [
                'key' => 'awaiting',
                'label' => 'Aguardando decisão',
                'description' => $decision === null
                    ? 'Todos os portões foram atendidos; registre a decisão humana.'
                    : 'As evidências mudaram; registre uma nova decisão.',
            ];
        }

        return $decision->decision === 'approved'
            ? [
                'key' => 'approved',
                'label' => 'Release aprovada',
                'description' => 'A decisão corresponde às evidências atuais.',
            ]
            : [
                'key' => 'held',
                'label' => 'Release em espera',
                'description' => 'Os portões foram atendidos, mas a decisão humana mantém a liberação em espera.',
            ];
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
