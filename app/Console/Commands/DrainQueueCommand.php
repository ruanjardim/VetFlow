<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DrainQueueCommand extends Command
{
    protected $signature = 'vetflow:queue:drain
        {--max-jobs=25 : Quantidade maxima de jobs processados nesta execucao}
        {--max-time=45 : Tempo maximo da execucao, em segundos}
        {--timeout=30 : Timeout de cada job, em segundos}
        {--tries=3 : Numero maximo de tentativas por job}';

    protected $description = 'Processa um lote curto da fila para hospedagens com cron e sem worker permanente.';

    public function handle(): int
    {
        $connection = (string) config('queue.default');

        if ($connection === '' || config("queue.connections.{$connection}") === null) {
            $this->error('A conexao padrao de fila nao esta configurada.');

            return self::FAILURE;
        }

        if (in_array($connection, ['sync', 'null', 'background', 'deferred'], true)) {
            $this->error("A conexao {$connection} nao pode ser drenada por cron.");

            return self::FAILURE;
        }

        $maxJobs = $this->validatedOption('max-jobs', 1, 100);
        $maxTime = $this->validatedOption('max-time', 1, 50);
        $timeout = $this->validatedOption('timeout', 1, 45);
        $tries = $this->validatedOption('tries', 1, 10);

        if ($maxJobs === null || $maxTime === null || $timeout === null || $tries === null) {
            return self::FAILURE;
        }

        if ($timeout >= $maxTime) {
            $this->error('O timeout de cada job deve ser menor que o tempo maximo da execucao.');

            return self::FAILURE;
        }

        $lock = Cache::lock('vetflow:queue:drain', $maxTime + 15);

        if (! $lock->get()) {
            $this->warn('Outra execucao da fila ainda esta ativa; este ciclo foi ignorado com seguranca.');

            return self::SUCCESS;
        }

        try {
            return $this->call('queue:work', [
                'connection' => $connection,
                '--queue' => (string) (config("queue.connections.{$connection}.queue") ?: 'default'),
                '--stop-when-empty' => true,
                '--max-jobs' => $maxJobs,
                '--max-time' => $maxTime,
                '--timeout' => $timeout,
                '--tries' => $tries,
                '--sleep' => 1,
            ]);
        } finally {
            $lock->release();
        }
    }

    private function validatedOption(string $name, int $minimum, int $maximum): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        if ($value === false || $value < $minimum || $value > $maximum) {
            $this->error("A opcao --{$name} deve estar entre {$minimum} e {$maximum}.");

            return null;
        }

        return $value;
    }
}
