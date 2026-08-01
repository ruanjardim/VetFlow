<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseReadinessCheckTest extends TestCase
{
    use RefreshDatabase;

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

        $this->artisan('vetflow:release:check')
            ->expectsOutputToContain('Release bloqueada por 1 verificacao(oes).')
            ->assertFailed();

        $this->artisan('vetflow:release:check', ['--backup-confirmed' => true])
            ->expectsOutputToContain('Verificacoes tecnicas de release aprovadas.')
            ->assertSuccessful();
    }
}
