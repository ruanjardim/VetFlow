<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackupRestoreVerificationTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        DB::purge('backup_source_test');
        DB::purge('backup_restore_test');
        File::delete($this->temporaryFiles);

        parent::tearDown();
    }

    public function test_isolated_restore_matching_the_snapshot_generates_passed_evidence(): void
    {
        [$source, $restore] = $this->prepareDatabases();
        $manifest = $this->temporaryPath('manifest.json');
        $evidence = $this->temporaryPath('evidence.json');

        $this->artisan('vetflow:backup:snapshot', [
            '--identifier' => 'pilot-2026-08-23',
            '--connection' => $source,
            '--output' => $manifest,
        ])->assertSuccessful();

        $this->artisan('vetflow:backup:verify', [
            '--manifest' => $manifest,
            '--connection' => $restore,
            '--evidence' => $evidence,
        ])->expectsOutputToContain('Restauracao isolada verificada com sucesso.')
            ->assertSuccessful();

        $result = json_decode(File::get($evidence), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('pilot-2026-08-23', $result['backup_identifier']);
        $this->assertNotEmpty($result['manifest_sha256']);
        $this->assertTrue(collect($result['checks'])->every('passed'));
        $this->assertStringNotContainsString('Clinica Controle', File::get($manifest));
    }

    public function test_restore_with_divergent_control_totals_is_rejected(): void
    {
        [$source, $restore] = $this->prepareDatabases();
        $manifest = $this->temporaryPath('divergent-manifest.json');
        $evidence = $this->temporaryPath('divergent-evidence.json');

        $this->artisan('vetflow:backup:snapshot', [
            '--identifier' => 'pilot-divergent',
            '--connection' => $source,
            '--output' => $manifest,
        ])->assertSuccessful();

        DB::connection($restore)->table('clinics')->insert([
            'name' => 'Registro divergente',
            'updated_at' => '2026-08-23 12:00:00',
        ]);

        $this->artisan('vetflow:backup:verify', [
            '--manifest' => $manifest,
            '--connection' => $restore,
            '--evidence' => $evidence,
        ])->expectsOutputToContain('Restauracao reprovada')
            ->assertFailed();

        $result = json_decode(File::get($evidence), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('failed', $result['status']);
        $this->assertTrue(collect($result['checks'])->contains(
            fn (array $check): bool => $check['name'] === 'Tabela clinics' && ! $check['passed']
        ));
    }

    public function test_verification_refuses_the_original_database_as_restore_target(): void
    {
        [$source] = $this->prepareDatabases();
        $manifest = $this->temporaryPath('same-database-manifest.json');

        $this->artisan('vetflow:backup:snapshot', [
            '--identifier' => 'pilot-same-database',
            '--connection' => $source,
            '--output' => $manifest,
        ])->assertSuccessful();

        $this->artisan('vetflow:backup:verify', [
            '--manifest' => $manifest,
            '--connection' => $source,
            '--evidence' => $this->temporaryPath('same-database-evidence.json'),
        ])->expectsOutputToContain('deve apontar para um banco isolado')
            ->assertFailed();
    }

    /** @return array{string, string} */
    private function prepareDatabases(): array
    {
        $sourcePath = $this->temporaryPath('source.sqlite');
        $restorePath = $this->temporaryPath('restore.sqlite');
        File::put($sourcePath, '');
        File::put($restorePath, '');

        config([
            'database.connections.backup_source_test' => $this->sqliteConfig($sourcePath),
            'database.connections.backup_restore_test' => $this->sqliteConfig($restorePath),
        ]);

        foreach (['backup_source_test', 'backup_restore_test'] as $connection) {
            Schema::connection($connection)->create('migrations', function (Blueprint $table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
            Schema::connection($connection)->create('clinics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamp('updated_at')->nullable();
            });
            DB::connection($connection)->table('migrations')->insert([
                'migration' => '2026_08_20_060000_create_audit_events_table',
                'batch' => 1,
            ]);
            DB::connection($connection)->table('clinics')->insert([
                'name' => 'Clinica Controle',
                'updated_at' => '2026-08-23 10:00:00',
            ]);
        }

        return ['backup_source_test', 'backup_restore_test'];
    }

    /** @return array<string, mixed> */
    private function sqliteConfig(string $database): array
    {
        return [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }

    private function temporaryPath(string $filename): string
    {
        $path = storage_path('framework/testing/'.$filename.'-'.uniqid('', true));
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
