<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseReadinessCheckTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryEvidence = [];

    protected function tearDown(): void
    {
        File::delete($this->temporaryEvidence);

        parent::tearDown();
    }

    public function test_local_release_checks_validate_runtime_dependencies(): void
    {
        Storage::fake('local');
        config([
            'app.key' => 'base64:local-readiness-key',
            'filesystems.default' => 'local',
            'logging.default' => 'single',
            'queue.default' => 'sync',
        ]);

        $this->artisan('vetflow:release:check')
            ->expectsOutputToContain('Verificacoes tecnicas de release aprovadas.')
            ->assertSuccessful();
    }

    public function test_production_release_requires_backup_and_non_sync_queue(): void
    {
        Storage::fake('local');
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.key' => 'base64:production-readiness-key',
            'app.debug' => false,
            'app.url' => 'https://vetflow.example',
            'filesystems.default' => 'local',
            'logging.default' => 'single',
            'queue.default' => 'database',
        ]);
        $runtimeEvidence = $this->runtimeEvidence('production');

        $this->artisan('vetflow:release:check', ['--runtime-evidence' => $runtimeEvidence])
            ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
            ->assertFailed();

        $this->artisan('vetflow:release:check', [
            '--backup-confirmed' => true,
            '--runtime-evidence' => $runtimeEvidence,
        ])
            ->expectsOutputToContain('Verificacoes tecnicas de release aprovadas.')
            ->assertSuccessful();
    }

    public function test_staging_cron_mode_requires_secure_bounded_configuration(): void
    {
        Storage::fake('local');
        $this->app->detectEnvironment(fn (): string => 'staging');
        config([
            'app.key' => 'base64:staging-readiness-key',
            'app.debug' => false,
            'app.url' => 'https://staging.vetflow.example',
            'filesystems.default' => 'local',
            'logging.default' => 'single',
            'queue.default' => 'database',
            'operations.queue.mode' => 'cron',
            'operations.queue.cron.enabled' => true,
            'operations.queue.cron.token' => 'short',
            'operations.queue.cron.header' => 'X-Cron-Auth',
            'operations.queue.cron.max_time' => 45,
            'operations.queue.cron.timeout' => 30,
        ]);
        $runtimeEvidence = $this->runtimeEvidence('staging', 'cron');

        $this->artisan('vetflow:release:check', [
            '--backup-confirmed' => true,
            '--runtime-evidence' => $runtimeEvidence,
        ])
            ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
            ->assertFailed();

        config([
            'operations.queue.cron.token' => str_repeat('c', 32),
            'operations.queue.cron.max_jobs' => 0,
        ]);

        $this->artisan('vetflow:release:check', [
            '--backup-confirmed' => true,
            '--runtime-evidence' => $runtimeEvidence,
        ])
            ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
            ->assertFailed();

        config(['operations.queue.cron.max_jobs' => 25]);

        $this->artisan('vetflow:release:check', [
            '--backup-confirmed' => true,
            '--runtime-evidence' => $runtimeEvidence,
        ])
            ->expectsOutputToContain('Verificacoes tecnicas de release aprovadas.')
            ->assertSuccessful();
    }

    public function test_production_release_accepts_fresh_restore_evidence(): void
    {
        Storage::fake('local');
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.key' => 'base64:production-readiness-key',
            'app.debug' => false,
            'app.url' => 'https://vetflow.example',
            'filesystems.default' => 'local',
            'logging.default' => 'single',
            'queue.default' => 'database',
        ]);
        $evidencePath = storage_path('framework/testing/release-backup-evidence.json');
        File::ensureDirectoryExists(dirname($evidencePath));
        $evidence = [
            'version' => 1,
            'status' => 'passed',
            'backup_identifier' => 'pilot-evidence',
            'verified_at' => now()->toIso8601String(),
            'manifest_sha256' => str_repeat('a', 64),
            'restore' => ['fingerprint' => str_repeat('b', 64)],
            'checks' => [['name' => 'Tabela clinics', 'passed' => false]],
        ];
        File::put($evidencePath, json_encode($evidence, JSON_THROW_ON_ERROR));
        $runtimeEvidence = $this->runtimeEvidence('production');

        try {
            $this->artisan('vetflow:release:check', [
                '--backup-evidence' => $evidencePath,
                '--runtime-evidence' => $runtimeEvidence,
            ])
                ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
                ->assertFailed();

            $evidence['checks'][0]['passed'] = true;
            File::put($evidencePath, json_encode($evidence, JSON_THROW_ON_ERROR));

            $this->artisan('vetflow:release:check', [
                '--backup-evidence' => $evidencePath,
                '--runtime-evidence' => $runtimeEvidence,
            ])
                ->expectsOutputToContain('Verificacoes tecnicas de release aprovadas.')
                ->assertSuccessful();
        } finally {
            File::delete($evidencePath);
        }
    }

    public function test_production_release_rejects_failed_runtime_evidence(): void
    {
        Storage::fake('local');
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.key' => 'base64:production-readiness-key',
            'app.debug' => false,
            'app.url' => 'https://vetflow.example',
            'filesystems.default' => 'local',
            'logging.default' => 'single',
            'queue.default' => 'database',
        ]);

        $this->artisan('vetflow:release:check', ['--backup-confirmed' => true])
            ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
            ->assertFailed();

        $evidencePath = $this->runtimeEvidence('production', failed: true);

        $this->artisan('vetflow:release:check', [
            '--backup-confirmed' => true,
            '--runtime-evidence' => $evidencePath,
        ])
            ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
            ->assertFailed();
    }

    private function runtimeEvidence(
        string $environment,
        string $queueMode = 'worker',
        bool $failed = false,
    ): string {
        $probeId = (string) Str::ulid();
        $path = storage_path('framework/testing/runtime-evidence-'.$probeId.'.json');
        $preparedAt = now()->subMinute();
        $checks = [
            ['name' => 'storage-sentinel-integrity', 'passed' => ! $failed],
            ['name' => 'queued-execution', 'passed' => true],
            ['name' => 'runtime-context', 'passed' => true],
            ['name' => 'evidence-freshness', 'passed' => true],
        ];
        $evidence = [
            'version' => 1,
            'status' => $failed ? 'failed' : 'passed',
            'probe_id' => $probeId,
            'prepared_at' => $preparedAt->toIso8601String(),
            'processed_at' => $preparedAt->addSeconds(20)->toIso8601String(),
            'verified_at' => now()->toIso8601String(),
            'environment' => $environment,
            'queue_connection' => 'database',
            'queue_mode' => $queueMode,
            'storage_disk' => 'local',
            'sentinel_sha256' => str_repeat('c', 64),
            'checks' => $checks,
        ];
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($evidence, JSON_THROW_ON_ERROR));
        $this->temporaryEvidence[] = $path;

        return $path;
    }
}
