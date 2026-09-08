<?php

namespace App\Modules\Operations\Services;

use Illuminate\Support\Collection;

class OperationsGuidanceService
{
    /**
     * @param  array<string, mixed>  $state
     * @return array{completed: int, total: int, next: ?string, items: array<int, array<string, mixed>>}
     */
    public function plan(array $state, bool $canExecute): array
    {
        $gates = collect($state['gates'])->keyBy('key');
        $failedPlatformChecks = collect($state['readiness']['checks'])
            ->reject(fn (array $check): bool => $check['passed'])
            ->reject(fn (array $check): bool => in_array(
                $check['check'],
                ['Identidade da release', 'Probe operacional', 'Backup restauravel'],
                true,
            ))
            ->pluck('check')
            ->values();
        $releaseAvailable = (bool) $state['release_available'];
        $gatesPassed = (bool) $state['gates_passed'];
        $decisionCurrent = (bool) ($state['decision']['current'] ?? false);
        $items = collect([
            $this->step(
                key: 'identity',
                label: 'Identificar a release',
                completed: $this->passed($gates, 'identity'),
                guidance: $this->passed($gates, 'identity')
                    ? 'O commit publicado foi reconhecido pelo ambiente.'
                    : 'Configure VETFLOW_RELEASE_SHA ou RENDER_GIT_COMMIT no provedor e publique novamente.',
                responsibility: 'Infraestrutura',
                anchor: 'release-identity',
                actionLabel: 'Revisar identidade',
                canAct: false,
            ),
            $this->step(
                key: 'platform',
                label: 'Validar a plataforma',
                completed: $this->passed($gates, 'platform'),
                guidance: $failedPlatformChecks->isEmpty()
                    ? 'Configuração, banco, migrations, fila, logs e armazenamento foram aprovados.'
                    : 'Corrija no ambiente: '.$failedPlatformChecks->implode(', ').'.',
                responsibility: 'Infraestrutura',
                anchor: 'technical-gates',
                actionLabel: 'Revisar diagnóstico',
                canAct: false,
            ),
            $this->step(
                key: 'runtime',
                label: 'Comprovar fila e armazenamento',
                completed: $this->passed($gates, 'runtime'),
                guidance: $this->passed($gates, 'runtime')
                    ? 'A evidência recente do probe operacional foi aprovada.'
                    : ($canExecute && $releaseAvailable
                        ? 'Prepare o probe, aguarde a fila e verifique o resultado nesta tela.'
                        : 'Solicite a um operador com permissão de execução que prepare e verifique o probe.'),
                responsibility: 'Operação técnica',
                anchor: 'runtime-probe',
                actionLabel: 'Ir para o probe',
                canAct: $canExecute && $releaseAvailable,
            ),
            $this->step(
                key: 'backup',
                label: 'Comprovar restauração do backup',
                completed: $this->passed($gates, 'backup'),
                guidance: $this->passed($gates, 'backup')
                    ? 'A evidência recente da restauração isolada foi aprovada.'
                    : ($canExecute && $releaseAvailable
                        ? 'Restaure o backup fora do banco ao vivo, gere o JSON de evidência e importe-o aqui.'
                        : 'Solicite a restauração isolada e a importação do JSON a um operador autorizado.'),
                responsibility: 'Operação técnica',
                anchor: 'backup-evidence',
                actionLabel: 'Ir para o backup',
                canAct: $canExecute && $releaseAvailable,
            ),
            $this->step(
                key: 'smoke',
                label: 'Concluir o smoke test',
                completed: $this->passed($gates, 'smoke'),
                guidance: $this->passed($gates, 'smoke')
                    ? 'Todos os itens de validação humana foram concluídos.'
                    : ($canExecute && $releaseAvailable
                        ? 'Revise os fluxos e registre os itens restantes individualmente.'
                        : 'Acompanhe os itens e solicite o registro a um operador autorizado.'),
                responsibility: 'Equipe de validação',
                anchor: 'smoke-checklist',
                actionLabel: 'Ir para o smoke test',
                canAct: $canExecute && $releaseAvailable,
            ),
            $this->step(
                key: 'decision',
                label: 'Registrar a decisão humana',
                completed: $decisionCurrent,
                guidance: $this->decisionGuidance($state, $gatesPassed, $canExecute),
                responsibility: 'Responsável pela release',
                anchor: 'release-decision',
                actionLabel: 'Ir para a decisão',
                canAct: $canExecute && $releaseAvailable && $gatesPassed,
            ),
        ]);
        $completed = $items->where('completed', true)->count();

        return [
            'completed' => $completed,
            'total' => $items->count(),
            'next' => $items->firstWhere('completed', false)['key'] ?? null,
            'items' => $items->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function step(
        string $key,
        string $label,
        bool $completed,
        string $guidance,
        string $responsibility,
        string $anchor,
        string $actionLabel,
        bool $canAct,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'completed' => $completed,
            'guidance' => $guidance,
            'responsibility' => $responsibility,
            'anchor' => $anchor,
            'action_label' => $actionLabel,
            'can_act' => ! $completed && $canAct,
            'status_label' => $completed ? 'Concluído' : ($canAct ? 'Ação disponível' : 'Aguardando'),
            'status_tone' => $completed ? 'success' : 'warning',
        ];
    }

    private function passed(Collection $gates, string $key): bool
    {
        return (bool) ($gates->get($key)['passed'] ?? false);
    }

    /** @param array<string, mixed> $state */
    private function decisionGuidance(array $state, bool $gatesPassed, bool $canExecute): string
    {
        if ($state['decision']['current'] ?? false) {
            return $state['decision']['decision'] === 'approved'
                ? 'A aprovação registrada corresponde às evidências atuais.'
                : 'A decisão atual mantém a release em espera.';
        }

        if (! $gatesPassed) {
            return 'Conclua os cinco portões operacionais antes de registrar a decisão final.';
        }

        return $canExecute
            ? 'Revise as evidências atuais e registre a aprovação ou a manutenção em espera.'
            : 'Solicite a decisão final a um responsável com permissão de execução.';
    }
}
