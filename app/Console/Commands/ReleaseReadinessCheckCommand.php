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
        {--backup-confirmed : Confirma que existe backup restauravel antes de uma release em producao}';

    protected $description = 'Verifica configuracao, banco, migrations, logs, fila, armazenamento e backup para uma release.';

    /**
     * @var array<int, array{check: string, status: string, detail: string}>
     */
    private array $checks = [];

    public function handle(): int
    {
        $this->checks = [];
        $production = app()->environment('production');

        $this->check(
            'Chave da aplicacao',
            filled(config('app.key')),
            filled(config('app.key')) ? 'APP_KEY configurada.' : 'APP_KEY ausente.'
        );
        $this->check(
            'Modo de depuracao',
            ! $production || ! config('app.debug'),
            $production && config('app.debug')
                ? 'APP_DEBUG deve ser false em producao.'
                : 'Configuracao compativel com o ambiente atual.'
        );
        $this->check(
            'URL da aplicacao',
            ! $production || str_starts_with((string) config('app.url'), 'https://'),
            $production
                ? (str_starts_with((string) config('app.url'), 'https://')
                    ? 'APP_URL usa HTTPS.'
                    : 'APP_URL deve usar HTTPS em producao.')
                : 'HTTPS sera obrigatorio quando APP_ENV=production.'
        );

        $this->checkDatabase();
        $this->checkMigrations();
        $this->checkLogging();
        $this->checkQueue($production);
        $this->checkStorage();
        $this->check(
            'Backup restauravel',
            ! $production || (bool) $this->option('backup-confirmed'),
            $production
                ? ((bool) $this->option('backup-confirmed')
                    ? 'Confirmado pelo operador para esta release.'
                    : 'Execute novamente com --backup-confirmed depois de validar o backup.')
                : 'Confirmacao obrigatoria somente em producao.'
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

    private function checkQueue(bool $production): void
    {
        try {
            $connection = (string) config('queue.default');
            $configured = $connection !== '' && config("queue.connections.{$connection}") !== null;
            $valid = $configured && (! $production || $connection !== 'sync');
            $detail = $configured
                ? "Conexao {$connection} configurada."
                : 'Conexao padrao de fila invalida.';

            if ($production && $connection === 'sync') {
                $detail = 'A fila sync nao e aceita para o piloto em producao.';
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
