<?php

namespace App\Console\Commands;

use App\Support\Operations\ReleaseReadinessService;
use Illuminate\Console\Command;

class ReleaseReadinessCheckCommand extends Command
{
    protected $signature = 'vetflow:release:check
        {--backup-confirmed : Confirma manualmente que existe backup restauravel}
        {--backup-evidence= : Evidencia JSON gerada por vetflow:backup:verify}
        {--runtime-evidence= : Evidencia JSON gerada por vetflow:runtime:probe}';

    protected $description = 'Verifica identidade, configuracao, banco, migrations, logs, fila, armazenamento e backup para uma release.';

    public function __construct(private readonly ReleaseReadinessService $readiness)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->readiness->evaluate(
            backupEvidencePath: (string) $this->option('backup-evidence'),
            runtimeEvidencePath: (string) $this->option('runtime-evidence'),
            backupConfirmed: (bool) $this->option('backup-confirmed'),
        );

        $this->table(
            ['Verificacao', 'Status', 'Detalhe'],
            collect($result['checks'])
                ->map(fn (array $check): array => [
                    $check['check'],
                    $check['status'],
                    $check['detail'],
                ])
                ->all()
        );

        if (! $result['passed']) {
            $this->error("Release bloqueada por {$result['failures']} verificacao(oes).");

            return self::FAILURE;
        }

        $this->info('Verificacoes tecnicas de release aprovadas.');

        return self::SUCCESS;
    }
}
