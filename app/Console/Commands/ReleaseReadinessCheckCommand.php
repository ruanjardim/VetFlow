<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReleaseReadinessCheckCommand extends Command
{
    protected $signature = 'vetflow:release:check
        {--backup-confirmed : Confirma que existe backup restauravel antes de uma release em staging/producao}';

    protected $description = 'Verifica configuracao, banco, migrations, logs, fila, armazenamento e backup para uma release.';

    /**
     * @var array<int, array{check: string, status: string, detail: string}>
     */
    private array $checks = [];

    public function handle(): int
    {
        $this->checks = [];
        $productionLike = app()->environment(['production', 'staging']);

        $this->check(
            'Chave da aplicacao',
            filled(config('app.key')),
            filled(config('app.key')) ? 'APP_KEY configurada.' : 'APP_KEY ausente.'
        );
        $this->check(
            'Modo de depuracao',
            ! $productionLike || ! config('app.debug'),
            $productionLike && config('app.debug')
                ? 'APP_DEBUG deve ser false em staging/producao.'
                : 'Configuracao compativel com o ambiente atual.'
        );
        $this->check(
            'URL da aplicacao',
            ! $productionLike || str_starts_with((string) config('app.url'), 'https://'),
            $productionLike
                ? (str_starts_with((string) config('app.url'), 'https://')
                    ? 'APP_URL usa HTTPS.'
                    : 'APP_URL deve usar HTTPS em staging/producao.')
                : 'HTTPS sera obrigatorio quando APP_ENV=staging/production.'
        );

        $this->checkDatabase();
        $this->checkMigrations();
        $this->checkLogging();
        $this->checkQueue($productionLike);
        $this->checkQueueProcessControl($productionLike);
        $this->checkStorage();
        $this->check(
            'Backup restauravel',
            ! $productionLike || (bool) $this->option('backup-confirmed'),
            $productionLike
                ? ((bool) $this->option('backup-confirmed')
                    ? 'Confirmado pelo operador para esta release.'
                    : 'Execute novamente com --backup-confirmed depois de validar o backup.')
                : 'Confirmacao obrigatoria somente em staging/producao.'
        );

        $this->table(
            ['Verificacao', 'Status', 'Detalhe'],
            collect($this->checks)
                ->map(fn (array $check): array => [
                    $check['check'],
                    $check['status'],
                    $check['detail'],
                ])
                ->all()
        );

        $failures = collect($this->checks)->where('status', 'FALHA')->count();

        if ($failures > 0) {
            $this->error("Release bloqueada por {$failures} verificacao(oes).");

            return self::FAILURE;
        }

        $this->info('Verificacoes tecnicas de release aprovadas.');

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            DB::select('select 1');
            $this->check('Banco de dados', true, 'Conexao e consulta basica aprovadas.');
        } catch (Throwable $exception) {
            $this->check('Banco de dados', false, $exception->getMessage());
        }
    }

    private function checkMigrations(): void
    {
        try {
            if (! Schema::hasTable('migrations')) {
                $this->check('Migrations', false, 'Tabela de controle de migrations ausente.');

                return;
            }

            $ran = DB::table('migrations')->pluck('migration')->all();
            $available = collect(File::files(database_path('migrations')))
                ->map(fn ($file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
                ->all();
            $pending = array_values(array_diff($available, $ran));

            $this->check(
                'Migrations',
                $pending === [],
                $pending === []
                    ? 'Nenhuma migration pendente.'
                    : 'Pendentes: '.implode(', ', $pending)
            );
        } catch (Throwable $exception) {
            $this->check('Migrations', false, $exception->getMessage());
        }
    }

    private function checkLogging(): void
    {
        $channel = (string) config('logging.default');
        $configured = $channel !== '' && config("logging.channels.{$channel}") !== null;

        $this->check(
            'Logging',
            $configured,
            $configured ? "Canal {$channel} configurado." : 'Canal padrao de log invalido.'
        );
    }

    private function checkQueue(bool $productionLike): void
    {
        try {
            $connection = (string) config('queue.default');
            $configured = $connection !== '' && config("queue.connections.{$connection}") !== null;
            $valid = $configured && (! $productionLike || $connection !== 'sync');
            $detail = $configured
                ? "Conexao {$connection} configurada."
                : 'Conexao padrao de fila invalida.';

            if ($productionLike && $connection === 'sync') {
                $detail = 'A fila sync nao e aceita para o piloto em staging/producao.';
            }

            if ($valid && $connection === 'database' && ! Schema::hasTable('jobs')) {
                $valid = false;
                $detail = 'A fila database exige a tabela jobs.';
            }

            $this->check('Fila', $valid, $detail);
        } catch (Throwable $exception) {
            $this->check('Fila', false, $exception->getMessage());
        }
    }

    private function checkQueueProcessControl(bool $productionLike): void
    {
        $mode = (string) config('operations.queue.mode', 'worker');

        if (! in_array($mode, ['worker', 'cron'], true)) {
            $this->check('Processamento da fila', false, 'VETFLOW_QUEUE_MODE deve ser worker ou cron.');

            return;
        }

        if (! $productionLike) {
            $this->check(
                'Processamento da fila',
                true,
                "Modo {$mode} sera validado integralmente em staging/producao."
            );

            return;
        }

        if ($mode === 'worker') {
            $this->check(
                'Processamento da fila',
                true,
                'Modo worker configurado; confirme o processo supervisionado no smoke test.'
            );

            return;
        }

        $connection = (string) config('queue.default');
        $enabled = (bool) config('operations.queue.cron.enabled');
        $token = (string) config('operations.queue.cron.token');
        $header = (string) config('operations.queue.cron.header');
        $maxJobs = (int) config('operations.queue.cron.max_jobs');
        $maxTime = (int) config('operations.queue.cron.max_time');
        $timeout = (int) config('operations.queue.cron.timeout');
        $tries = (int) config('operations.queue.cron.tries');
        $valid = $connection === 'database'
            && $enabled
            && mb_strlen($token) >= 32
            && preg_match('/^[A-Za-z0-9-]+$/', $header) === 1
            && $maxJobs >= 1
            && $maxJobs <= 100
            && $maxTime >= 1
            && $maxTime <= 50
            && $timeout >= 1
            && $timeout < $maxTime
            && $tries >= 1
            && $tries <= 10;

        $this->check(
            'Processamento da fila',
            $valid,
            $valid
                ? 'Cron controlado habilitado com fila database, token e limites seguros.'
                : 'O modo cron exige fila database, endpoint habilitado, token de 32+ caracteres, header valido e limites seguros.'
        );
    }

    private function checkStorage(): void
    {
        $disk = (string) config('filesystems.default');
        $path = '.vetflow/readiness-'.Str::ulid().'.tmp';

        try {
            $storage = Storage::disk($disk);
            $written = $storage->put($path, 'vetflow-release-check');
            $exists = $written && $storage->exists($path);
            $storage->delete($path);

            $this->check(
                'Armazenamento',
                $exists,
                $exists ? "Disco {$disk} permite escrita e remocao." : "Falha de escrita no disco {$disk}."
            );
        } catch (Throwable $exception) {
            $this->check('Armazenamento', false, $exception->getMessage());
        }
    }

    private function check(string $name, bool $passed, string $detail): void
    {
        $this->checks[] = [
            'check' => $name,
            'status' => $passed ? 'OK' : 'FALHA',
            'detail' => $detail,
        ];
    }
}
