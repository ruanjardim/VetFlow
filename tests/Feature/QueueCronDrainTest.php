<?php

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueCronProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Cache::put('vetflow-queue-cron-probe', true, 60);
    }
}

class QueueCronDrainTest extends TestCase
{
    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        if ($this->databasePath !== null) {
            DB::purge('sqlite');
            File::delete($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_controlled_command_processes_a_bounded_database_queue_batch(): void
    {
        $this->usePersistentSqliteDatabase();
        config(['queue.default' => 'database']);
        Cache::forget('vetflow-queue-cron-probe');
        QueueCronProbeJob::dispatch();

        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('vetflow:queue:drain', [
            '--max-jobs' => 1,
            '--max-time' => 5,
            '--timeout' => 3,
            '--tries' => 1,
        ])->assertSuccessful();

        $this->assertTrue(Cache::get('vetflow-queue-cron-probe'));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_controlled_command_rejects_a_synchronous_queue(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('vetflow:queue:drain')
            ->expectsOutputToContain('A conexao sync nao pode ser drenada por cron.')
            ->assertFailed();
    }

    public function test_cron_endpoint_is_hidden_when_disabled(): void
    {
        config(['operations.queue.cron.enabled' => false]);

        $this->get('/ops/cron/queue')->assertNotFound();
    }

    public function test_cron_endpoint_rejects_an_invalid_token(): void
    {
        config([
            'operations.queue.cron.enabled' => true,
            'operations.queue.cron.token' => str_repeat('a', 32),
        ]);

        $this->withHeader('X-Cron-Auth', 'invalid-token')
            ->get('/ops/cron/queue')
            ->assertForbidden();
    }

    public function test_cron_endpoint_runs_an_empty_queue_without_exposing_output(): void
    {
        $this->usePersistentSqliteDatabase();
        $token = str_repeat('b', 32);
        config([
            'queue.default' => 'database',
            'operations.queue.cron.enabled' => true,
            'operations.queue.cron.token' => $token,
            'operations.queue.cron.header' => 'X-Cron-Auth',
            'operations.queue.cron.max_jobs' => 1,
            'operations.queue.cron.max_time' => 5,
            'operations.queue.cron.timeout' => 3,
            'operations.queue.cron.tries' => 1,
        ]);

        $response = $this->withHeader('X-Cron-Auth', $token)
            ->get('/ops/cron/queue')
            ->assertNoContent();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function usePersistentSqliteDatabase(): void
    {
        $this->databasePath = storage_path('framework/testing/queue-cron-'.Str::uuid().'.sqlite');
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
