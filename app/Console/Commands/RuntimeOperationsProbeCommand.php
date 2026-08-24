<?php

namespace App\Console\Commands;

use App\Support\Operations\RuntimeOperationsProbeService;
use Illuminate\Console\Command;
use Throwable;

class RuntimeOperationsProbeCommand extends Command
{
    protected $signature = 'vetflow:runtime:probe
        {--probe= : ULID do probe; gerado automaticamente no preparo}
        {--verify : Verifica o resultado processado pela fila}
        {--evidence= : Caminho da evidencia JSON verificada}';

    protected $description = 'Comprova storage persistente e processamento assincrono com dados sinteticos.';

    public function __construct(private readonly RuntimeOperationsProbeService $probes)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if ((bool) $this->option('verify')) {
                return $this->verify();
            }

            if (filled($this->option('evidence'))) {
                $this->error('A opcao --evidence so pode ser usada com --verify.');

                return self::FAILURE;
            }

            $probe = $this->probes->prepare($this->optionalString('probe'));

            $this->table(['Campo', 'Valor'], [
                ['Probe', $probe['probe_id']],
                ['Preparado em', $probe['prepared_at']],
                ['Fila', $probe['queue_connection'].' ('.$probe['queue_mode'].')'],
                ['Storage', $probe['storage_disk']],
            ]);
            $this->info('Probe preparado e enviado para a fila.');
            $this->line('Depois do worker/cron, verifique com:');
            $this->line('php artisan vetflow:runtime:probe --verify --probe='.$probe['probe_id']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Falha no probe operacional: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function verify(): int
    {
        $probeId = $this->optionalString('probe');

        if ($probeId === null) {
            $this->error('Informe --probe com o ULID preparado.');

            return self::FAILURE;
        }

        $evidence = $this->probes->verify($probeId);
        $evidencePath = $this->probes->writeEvidence($evidence, $this->optionalString('evidence'));

        $this->probes->cleanup($probeId);

        $this->table(
            ['Verificacao', 'Status'],
            collect($evidence['checks'])->map(fn (array $check): array => [
                $check['name'],
                $check['passed'] ? 'OK' : 'FALHA',
            ])->all()
        );
        $this->line('Evidencia: '.$evidencePath);
        $this->info('Probe operacional aprovado; artefatos sinteticos removidos.');

        return self::SUCCESS;
    }

    private function optionalString(string $option): ?string
    {
        $value = trim((string) $this->option($option));

        return $value !== '' ? $value : null;
    }
}
