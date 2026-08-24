<?php

namespace App\Support\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReleaseReadinessService
{
    public function __construct(
        private readonly ReleaseIdentityService $releaseIdentity,
        private readonly RuntimeOperationsProbeService $runtimeProbe,
    ) {}

    /**
     * @return array{passed: bool, failures: int, production_like: bool, checks: array<int, array{check: string, status: string, passed: bool, detail: string}>}
     */
    public function evaluate(
        ?string $backupEvidencePath = null,
        ?string $runtimeEvidencePath = null,
        bool $backupConfirmed = false,
    ): array {
        $productionLike = app()->environment(['production', 'staging']);
        $checks = [];
        $releaseSha = $this->releaseIdentity->sha();

        $checks[] = $this->check(
            'Identidade da release',
            ! $productionLike || $releaseSha !== null,
            $releaseSha !== null
                ? 'Commit '.substr($releaseSha, 0, 7).' identificado.'
                : ($productionLike
                    ? 'VETFLOW_RELEASE_SHA ou RENDER_GIT_COMMIT deve conter um SHA Git completo.'
                    : 'SHA completo sera obrigatorio em staging/producao.')
        );
        $checks[] = $this->check(
            'Chave da aplicacao',
            filled(config('app.key')),
            filled(config('app.key')) ? 'APP_KEY configurada.' : 'APP_KEY ausente.'
        );
        $checks[] = $this->check(
            'Modo de depuracao',
            ! $productionLike || ! config('app.debug'),
            $productionLike && config('app.debug')
                ? 'APP_DEBUG deve ser false em staging/producao.'
                : 'Configuracao compativel com o ambiente atual.'
        );
        $checks[] = $this->check(
            'URL da aplicacao',
            ! $productionLike || str_starts_with((string) config('app.url'), 'https://'),
            $productionLike
                ? (str_starts_with((string) config('app.url'), 'https://')
                    ? 'APP_URL usa HTTPS.'
                    : 'APP_URL deve usar HTTPS em staging/producao.')
                : 'HTTPS sera obrigatorio quando APP_ENV=staging/production.'
        );
        $checks[] = $this->databaseCheck();
        $checks[] = $this->migrationsCheck();
        $checks[] = $this->loggingCheck();
        $checks[] = $this->queueCheck($productionLike);
        $checks[] = $this->queueProcessCheck($productionLike);
        $checks[] = $this->storageCheck();
        [$runtimePassed, $runtimeDetail] = $this->runtimeEvidenceCheck(
            $productionLike,
            trim((string) $runtimeEvidencePath)
        );
        $checks[] = $this->check('Probe operacional', $runtimePassed, $runtimeDetail);
        [$backupPassed, $backupDetail] = $this->backupEvidenceCheck(
            $productionLike,
            trim((string) $backupEvidencePath),
            $backupConfirmed
        );
        $checks[] = $this->check('Backup restauravel', $backupPassed, $backupDetail);
        $failures = collect($checks)->where('passed', false)->count();

        return [
            'passed' => $failures === 0,
            'failures' => $failures,
            'production_like' => $productionLike,
            'checks' => $checks,
        ];
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function databaseCheck(): array
    {
        try {
            DB::select('select 1');

            return $this->check('Banco de dados', true, 'Conexao e consulta basica aprovadas.');
        } catch (Throwable) {
            return $this->check('Banco de dados', false, 'A conexao ou a consulta basica falhou.');
        }
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function migrationsCheck(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return $this->check('Migrations', false, 'Tabela de controle de migrations ausente.');
            }

            $ran = DB::table('migrations')->pluck('migration')->all();
            $available = collect(File::files(database_path('migrations')))
                ->map(fn ($file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
                ->all();
            $pending = array_values(array_diff($available, $ran));

            return $this->check(
                'Migrations',
                $pending === [],
                $pending === []
                    ? 'Nenhuma migration pendente.'
                    : count($pending).' migration(oes) pendente(s).'
            );
        } catch (Throwable) {
            return $this->check('Migrations', false, 'Nao foi possivel consultar o historico de migrations.');
        }
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function loggingCheck(): array
    {
        $channel = (string) config('logging.default');
        $configured = $channel !== '' && config("logging.channels.{$channel}") !== null;

        return $this->check(
            'Logging',
            $configured,
            $configured ? "Canal {$channel} configurado." : 'Canal padrao de log invalido.'
        );
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function queueCheck(bool $productionLike): array
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

            return $this->check('Fila', $valid, $detail);
        } catch (Throwable) {
            return $this->check('Fila', false, 'Nao foi possivel validar a configuracao da fila.');
        }
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function queueProcessCheck(bool $productionLike): array
    {
        $mode = (string) config('operations.queue.mode', 'worker');

        if (! in_array($mode, ['worker', 'cron'], true)) {
            return $this->check('Processamento da fila', false, 'VETFLOW_QUEUE_MODE deve ser worker ou cron.');
        }

        if (! $productionLike) {
            return $this->check(
                'Processamento da fila',
                true,
                "Modo {$mode} sera validado integralmente em staging/producao."
            );
        }

        if ($mode === 'worker') {
            return $this->check(
                'Processamento da fila',
                true,
                'Modo worker configurado; confirme o processo supervisionado no smoke test.'
            );
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

        return $this->check(
            'Processamento da fila',
            $valid,
            $valid
                ? 'Cron controlado habilitado com fila database, token e limites seguros.'
                : 'O modo cron exige fila database, endpoint habilitado, token de 32+ caracteres, header valido e limites seguros.'
        );
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function storageCheck(): array
    {
        $disk = (string) config('filesystems.default');
        $path = '.vetflow/readiness-'.Str::ulid().'.tmp';

        try {
            $storage = Storage::disk($disk);
            $written = $storage->put($path, 'vetflow-release-check');
            $exists = $written && $storage->exists($path);
            $storage->delete($path);

            return $this->check(
                'Armazenamento',
                $exists,
                $exists ? "Disco {$disk} permite escrita e remocao." : "Falha de escrita no disco {$disk}."
            );
        } catch (Throwable) {
            return $this->check('Armazenamento', false, "Falha ao validar o disco {$disk}.");
        }
    }

    /** @return array{bool, string} */
    private function backupEvidenceCheck(bool $productionLike, string $evidencePath, bool $backupConfirmed): array
    {
        if (! $productionLike) {
            return [true, 'Confirmacao obrigatoria somente em staging/producao.'];
        }

        if ($evidencePath === '') {
            return $backupConfirmed
                ? [true, 'Confirmado manualmente pelo operador para esta release.']
                : [false, 'Evidencia recente de restauracao ainda nao foi informada.'];
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
    private function runtimeEvidenceCheck(bool $productionLike, string $evidencePath): array
    {
        if (! $productionLike) {
            return [true, 'Evidencia obrigatoria somente em staging/producao.'];
        }

        if ($evidencePath === '') {
            return [false, 'Evidencia recente do probe operacional ainda nao foi informada.'];
        }

        try {
            if (! File::isFile($evidencePath)) {
                return [false, 'Arquivo de evidencia operacional nao encontrado.'];
            }

            $evidence = json_decode(File::get($evidencePath), true, flags: JSON_THROW_ON_ERROR);

            return $this->runtimeProbe->validateEvidence($evidence, app()->environment());
        } catch (Throwable) {
            return [false, 'Evidencia operacional invalida ou ilegivel.'];
        }
    }

    /** @return array{check: string, status: string, passed: bool, detail: string} */
    private function check(string $name, bool $passed, string $detail): array
    {
        return [
            'check' => $name,
            'status' => $passed ? 'OK' : 'FALHA',
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
