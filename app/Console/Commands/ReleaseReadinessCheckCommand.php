<?php

namespace App\Console\Commands;

use App\Support\Operations\ReleaseIdentityService;
use App\Support\Operations\RuntimeOperationsProbeService;
use Carbon\CarbonImmutable;
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
        {--backup-confirmed : Confirma manualmente que existe backup restauravel}
        {--backup-evidence= : Evidencia JSON gerada por vetflow:backup:verify}
        {--runtime-evidence= : Evidencia JSON gerada por vetflow:runtime:probe}';

    protected $description = 'Verifica identidade, configuracao, banco, migrations, logs, fila, armazenamento e backup para uma release.';

    /**
     * @var array<int, array{check: string, status: string, detail: string}>
     */
    private array $checks = [];

    public function handle(): int
    {
        $this->checks = [];
        $productionLike = app()->environment(['production', 'staging']);

        $releaseSha = app(ReleaseIdentityService::class)->sha();
        $this->check(
            'Identidade da release',
            ! $productionLike || $releaseSha !== null,
            $releaseSha !== null
                ? 'Commit '.substr($releaseSha, 0, 7).' identificado.'
                : ($productionLike
                    ? 'VETFLOW_RELEASE_SHA ou RENDER_GIT_COMMIT deve conter um SHA Git completo.'
                    : 'SHA completo sera obrigatorio em staging/producao.')
        );

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
        [$runtimePassed, $runtimeDetail] = $this->checkRuntimeEvidence($productionLike);
        $this->check('Probe operacional', $runtimePassed, $runtimeDetail);
        [$backupPassed, $backupDetail] = $this->checkBackupEvidence($productionLike);
        $this->check('Backup restauravel', $backupPassed, $backupDetail);

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

    /** @return array{bool, string} */
    private function checkBackupEvidence(bool $productionLike): array
    {
        if (! $productionLike) {
            return [true, 'Confirmacao obrigatoria somente em staging/producao.'];
        }

        $evidencePath = trim((string) $this->option('backup-evidence'));

        if ($evidencePath === '') {
            return (bool) $this->option('backup-confirmed')
                ? [true, 'Confirmado manualmente pelo operador para esta release.']
                : [false, 'Informe --backup-evidence depois do teste isolado ou use a confirmacao manual.'];
        }

        try {
            if (! File::isFile($evidencePath)) {
                return [false, 'Arquivo de evidencia de restauracao nao encontrado.'];
            }

            $evidence = json_decode(File::get($evidencePath), true, flags: JSON_THROW_ON_ERROR);
            $verifiedAt = CarbonImmutable::parse((string) ($evidence['verified_at'] ?? ''));
            $maxAgeDays = max(1, (int) config('operations.backup.evidence_max_age_days', 30));
            $fresh = $verifiedAt->betweenIncluded(now()->subDays($maxAgeDays), now()->addMinutes(5));
            $checks = collect($evidence['checks'] ?? []);
            $valid = ($evidence['version'] ?? null) === 1
                && ($evidence['status'] ?? null) === 'passed'
                && is_string($evidence['backup_identifier'] ?? null)
                && preg_match('/^[a-f0-9]{64}$/', (string) ($evidence['manifest_sha256'] ?? '')) === 1
                && preg_match('/^[a-f0-9]{64}$/', (string) ($evidence['restore']['fingerprint'] ?? '')) === 1
                && $checks->isNotEmpty()
                && $checks->every(fn ($check): bool => is_array($check) && ($check['passed'] ?? false) === true)
                && $fresh;

            return $valid
                ? [true, 'Restauracao isolada comprovada pela evidencia '.$evidence['backup_identifier'].'.']
                : [false, "Evidencia invalida, reprovada ou com mais de {$maxAgeDays} dias."];
        } catch (Throwable) {
            return [false, 'Evidencia de restauracao invalida ou ilegivel.'];
        }
    }

    /** @return array{bool, string} */
    private function checkRuntimeEvidence(bool $productionLike): array
    {
        if (! $productionLike) {
            return [true, 'Evidencia obrigatoria somente em staging/producao.'];
        }

        $evidencePath = trim((string) $this->option('runtime-evidence'));

        if ($evidencePath === '') {
            return [false, 'Informe --runtime-evidence depois de processar o probe pela fila.'];
        }

        try {
            if (! File::isFile($evidencePath)) {
                return [false, 'Arquivo de evidencia operacional nao encontrado.'];
            }

            $evidence = json_decode(File::get($evidencePath), true, flags: JSON_THROW_ON_ERROR);

            return app(RuntimeOperationsProbeService::class)->validateEvidence(
                $evidence,
                app()->environment()
            );
        } catch (Throwable) {
            return [false, 'Evidencia operacional invalida ou ilegivel.'];
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
