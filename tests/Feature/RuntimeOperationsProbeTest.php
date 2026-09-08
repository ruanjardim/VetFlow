<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RuntimeOperationsProbeTest extends TestCase
{
    private ?string $databasePath = null;

    /** @var array<int, string> */
    private array $evidencePaths = [];

    protected function tearDown(): void
    {
        if ($this->databasePath !== null) {
            DB::purge('sqlite');
            File::delete($this->databasePath);
        }

        File::delete($this->evidencePaths);

        parent::tearDown();
    }

    public function test_probe_crosses_database_queue_and_generates_evidence(): void
    {
        $this->usePersistentSqliteDatabase();
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'queue.default' => 'database',
            'operations.queue.mode' => 'worker',
            'operations.runtime_probe.disk' => 'local',
            'operations.runtime_probe.evidence_max_age_minutes' => 180,
        ]);
        $probeId = (string) Str::ulid();
        $directory = 'vetflow/runtime-probes/'.$probeId;
        $evidencePath = storage_path('framework/testing/runtime-probe-'.$probeId.'.json');
        $this->evidencePaths[] = $evidencePath;

        $this->artisan('vetflow:runtime:probe', ['--probe' => $probeId])
            ->expectsOutputToContain('Probe preparado e enviado para a fila.')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($directory.'/sentinel.json');
        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('vetflow:queue:drain', [
            '--max-jobs' => 1,
            '--max-time' => 5,
            '--timeout' => 3,
            '--tries' => 1,
        ])->assertSuccessful();

        Storage::disk('local')->assertExists($directory.'/result.json');
        $this->assertDatabaseCount('jobs', 0);

        $this->artisan('vetflow:runtime:probe', [
            '--verify' => true,
            '--probe' => $probeId,
            '--evidence' => $evidencePath,
        ])
            ->expectsOutputToContain('Probe operacional aprovado; artefatos sinteticos removidos.')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($directory.'/sentinel.json');
        Storage::disk('local')->assertMissing($directory.'/result.json');
        $this->assertFileExists($evidencePath);
        $evidence = json_decode(File::get($evidencePath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $evidence['status']);
        $this->assertSame($probeId, $evidence['probe_id']);
        $this->assertSame('database', $evidence['queue_connection']);
        $this->assertTrue(collect($evidence['checks'])->every(fn (array $check): bool => $check['passed']));
    }

    public function test_probe_rejects_sync_queue_without_leaving_artifacts(): void
    {
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'queue.default' => 'sync',
            'operations.runtime_probe.disk' => 'local',
        ]);
        $probeId = (string) Str::ulid();

        $this->artisan('vetflow:runtime:probe', ['--probe' => $probeId])
            ->expectsOutputToContain('A conexao sync nao comprova processamento assincrono.')
            ->assertFailed();

        Storage::disk('local')->assertMissing('vetflow/runtime-probes/'.$probeId.'/sentinel.json');
    }

    public function test_verification_waits_for_the_queued_result(): void
    {
        $this->usePersistentSqliteDatabase();
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'queue.default' => 'database',
            'operations.queue.mode' => 'worker',
            'operations.runtime_probe.disk' => 'local',
        ]);
        $probeId = (string) Str::ulid();

        $this->artisan('vetflow:runtime:probe', ['--probe' => $probeId])->assertSuccessful();

        $this->artisan('vetflow:runtime:probe', [
            '--verify' => true,
            '--probe' => $probeId,
        ])
            ->expectsOutputToContain('O probe ainda nao foi processado pela fila.')
            ->assertFailed();

        Storage::disk('local')->assertExists('vetflow/runtime-probes/'.$probeId.'/sentinel.json');
    }

    private function usePersistentSqliteDatabase(): void
    {
        $this->databasePath = storage_path('framework/testing/runtime-probe-'.Str::uuid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->databasePath));
        touch($this->databasePath);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);
        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }
}
