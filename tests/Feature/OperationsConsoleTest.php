<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Operations\Models\OperationsBackupEvidenceEvent;
use App\Modules\Operations\Models\OperationsReleaseDecision;
use App\Modules\Operations\Models\OperationsRuntimeProbeEvent;
use App\Modules\Operations\Models\OperationsSmokeCheck;
use App\Support\Operations\RuntimeOperationsProbeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_console_is_permission_protected(): void
    {
        $user = User::factory()->create(['active' => true]);

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertForbidden();
    }

    public function test_authorized_operator_can_see_safe_release_context(): void
    {
        Storage::fake('local');
        $sha = str_repeat('b', 40);
        config([
            'operations.release.sha' => $sha,
            'operations.queue.mode' => 'worker',
            'queue.default' => 'database',
            'filesystems.default' => 'local',
        ]);
        $user = $this->userWithPermission('operations.readiness');

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Central de operações')
            ->assertSee(substr($sha, 0, 7))
            ->assertSee('worker / database')
            ->assertSee('local')
            ->assertSee('Portões da release')
            ->assertSee('Banco de dados')
            ->assertSee('Nenhuma migration pendente.')
            ->assertDontSee($sha);
    }

    public function test_console_presents_latest_evidence_without_exposing_private_paths(): void
    {
        Storage::fake('local');
        $directory = storage_path('framework/testing/operations-evidence-'.Str::uuid());
        File::ensureDirectoryExists($directory.'/backup');
        File::ensureDirectoryExists($directory.'/runtime');
        config([
            'operations.backup.evidence_directory' => $directory.'/backup',
            'operations.runtime_probe.evidence_directory' => $directory.'/runtime',
            'filesystems.default' => 'local',
        ]);
        File::put($directory.'/backup/pilot-backup-evidence.json', json_encode([
            'backup_identifier' => 'pilot-backup-2026-08-24',
            'status' => 'passed',
            'verified_at' => now()->toIso8601String(),
            'checks' => [['name' => 'tables', 'passed' => true]],
        ], JSON_THROW_ON_ERROR));
        File::put($directory.'/runtime/01K3PROBE00000000000000000-evidence.json', json_encode([
            'probe_id' => '01K3PROBE00000000000000000',
            'status' => 'passed',
            'verified_at' => now()->toIso8601String(),
            'checks' => [['name' => 'queue', 'passed' => true]],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->actingAs($this->userWithPermission('operations.readiness'))
                ->get(route('operations.index'))
                ->assertOk()
                ->assertSee('pilot-backup-2026-08-24')
                ->assertSee('01K3PROBE00000000000000000')
                ->assertSee('1 verificações')
                ->assertDontSee($directory);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_smoke_check_decisions_are_append_only_and_clinic_scoped(): void
    {
        Storage::fake('local');
        $sha = str_repeat('e', 40);
        config(['operations.release.sha' => $sha, 'filesystems.default' => 'local']);
        $clinicA = Clinic::query()->create([
            'corporate_name' => 'Clínica Operações A',
            'trade_name' => 'Clínica Operações A',
            'cnpj' => '00000000000901',
            'active' => true,
        ]);
        $clinicB = Clinic::query()->create([
            'corporate_name' => 'Clínica Operações B',
            'trade_name' => 'Clínica Operações B',
            'cnpj' => '00000000000902',
            'active' => true,
        ]);
        $operatorA = $this->userWithPermission(['operations.readiness', 'operations.execute'], $clinicA);
        $operatorB = $this->userWithPermission(['operations.readiness', 'operations.execute'], $clinicB);

        $this->actingAs($operatorA)->post(
            route('operations.smoke-checks.store', 'health_endpoint'),
            ['action' => 'complete', 'note' => 'Saúde validada na clínica A.']
        )->assertRedirect();
        $this->post(
            route('operations.smoke-checks.store', 'health_endpoint'),
            ['action' => 'reopen', 'note' => 'Reaberto para nova conferência.']
        )->assertRedirect();

        $this->assertDatabaseCount('operations_smoke_checks', 2);
        $this->assertDatabaseHas('operations_smoke_checks', [
            'clinic_id' => $clinicA->id,
            'release_sha' => $sha,
            'check_key' => 'health_endpoint',
            'completed' => false,
        ]);

        $this->actingAs($operatorB)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertDontSee('Saúde validada na clínica A.')
            ->assertDontSee('Reaberto para nova conferência.');
    }

    public function test_release_decision_is_bound_to_current_evidence_and_exported_without_cache(): void
    {
        Storage::fake('local');
        $sha = str_repeat('f', 40);
        config([
            'operations.release.sha' => $sha,
            'filesystems.default' => 'local',
            'logging.default' => 'single',
            'queue.default' => 'sync',
        ]);
        $operator = $this->userWithPermission(['operations.readiness', 'operations.execute']);
        $checkKeys = [
            'health_endpoint',
            'release_identity',
            'tenant_login',
            'implementation_scope',
            'product_lookup',
            'nfe_preview',
            'stock_entry',
            'draft_sale',
            'completed_sale',
            'async_queue',
            'disposable_asset',
            'logs_review',
        ];

        foreach ($checkKeys as $checkKey) {
            $this->actingAs($operator)->post(
                route('operations.smoke-checks.store', $checkKey),
                ['action' => 'complete']
            )->assertRedirect();
        }

        $this->post(route('operations.decision.store'), [
            'decision' => 'approved',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $decision = OperationsReleaseDecision::query()->firstOrFail();
        $this->assertSame('approved', $decision->decision);
        $this->assertSame($sha, $decision->release_sha);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $decision->evidence_hash);

        $response = $this->get(route('operations.report.json'))
            ->assertOk()
            ->assertJsonPath('release.sha', $sha)
            ->assertJsonPath('status.key', 'approved')
            ->assertJsonPath('decision.current', true)
            ->assertJsonPath('smoke_checklist.completed', 12);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->post(route('operations.smoke-checks.store', 'logs_review'), [
            'action' => 'reopen',
            'note' => 'Nova revisão necessária.',
        ])->assertRedirect();

        $this->get(route('operations.report.json'))
            ->assertOk()
            ->assertJsonPath('status.key', 'blocked')
            ->assertJsonPath('decision.current', false)
            ->assertJsonPath('smoke_checklist.completed', 11);
        $this->assertDatabaseCount('operations_release_decisions', 1);
    }

    public function test_operator_can_prepare_and_verify_a_runtime_probe_from_the_console(): void
    {
        Storage::fake('local');
        $sha = str_repeat('c', 40);
        $directory = storage_path('framework/testing/operations-runtime-'.Str::uuid());
        config([
            'operations.release.sha' => $sha,
            'operations.queue.mode' => 'worker',
            'operations.runtime_probe.disk' => 'local',
            'operations.runtime_probe.evidence_directory' => $directory,
            'queue.default' => 'database',
            'filesystems.default' => 'local',
        ]);
        $operator = $this->userWithPermission(['operations.readiness', 'operations.execute']);

        try {
            $this->actingAs($operator)
                ->post(route('operations.runtime-probes.prepare'))
                ->assertRedirect()
                ->assertSessionHas('success');

            $prepared = OperationsRuntimeProbeEvent::query()->firstOrFail();
            $this->assertSame('prepared', $prepared->event);
            $this->assertSame($operator->clinic_id, $prepared->clinic_id);
            $this->assertSame($sha, $prepared->release_sha);
            Storage::disk('local')->assertExists(
                'vetflow/runtime-probes/'.$prepared->probe_id.'/sentinel.json'
            );

            $sentinel = Storage::disk('local')->get(
                'vetflow/runtime-probes/'.$prepared->probe_id.'/sentinel.json'
            );
            app(RuntimeOperationsProbeService::class)->process(
                $prepared->probe_id,
                'local',
                hash('sha256', $sentinel),
            );

            $this->post(route('operations.runtime-probes.verify', $prepared->probe_id))
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseCount('operations_runtime_probe_events', 2);
            $this->assertDatabaseHas('operations_runtime_probe_events', [
                'probe_id' => $prepared->probe_id,
                'event' => 'verified',
                'release_sha' => $sha,
            ]);
            $this->assertFileExists($directory.'/'.$prepared->probe_id.'-evidence.json');
            Storage::disk('local')->assertMissing('vetflow/runtime-probes/'.$prepared->probe_id);

            $this->get(route('operations.index'))
                ->assertOk()
                ->assertSee($prepared->probe_id)
                ->assertSee('4 verificações operacionais aprovadas.')
                ->assertDontSee(hash('sha256', $sentinel));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_runtime_probe_runs_are_isolated_by_clinic(): void
    {
        Storage::fake('local');
        config([
            'operations.release.sha' => str_repeat('d', 40),
            'operations.queue.mode' => 'worker',
            'operations.runtime_probe.disk' => 'local',
            'queue.default' => 'database',
            'filesystems.default' => 'local',
        ]);
        $clinicA = Clinic::query()->create([
            'corporate_name' => 'Clínica Probe A',
            'trade_name' => 'Clínica Probe A',
            'cnpj' => '00000000000911',
            'active' => true,
        ]);
        $clinicB = Clinic::query()->create([
            'corporate_name' => 'Clínica Probe B',
            'trade_name' => 'Clínica Probe B',
            'cnpj' => '00000000000912',
            'active' => true,
        ]);
        $operatorA = $this->userWithPermission(['operations.readiness', 'operations.execute'], $clinicA);
        $operatorB = $this->userWithPermission(['operations.readiness', 'operations.execute'], $clinicB);

        $this->actingAs($operatorA)
            ->post(route('operations.runtime-probes.prepare'))
            ->assertRedirect()
            ->assertSessionHas('success');
        $probe = OperationsRuntimeProbeEvent::query()->firstOrFail();

        $this->actingAs($operatorB)
            ->post(route('operations.runtime-probes.verify', $probe->probe_id))
            ->assertRedirect()
            ->assertSessionHasErrors('runtime_probe');

        $this->assertDatabaseCount('operations_runtime_probe_events', 1);
        $this->get(route('operations.index'))
            ->assertOk()
            ->assertDontSee($probe->probe_id);
    }

    public function test_release_cannot_be_approved_with_pending_gates(): void
    {
        Storage::fake('local');
        config([
            'operations.release.sha' => str_repeat('a', 40),
            'filesystems.default' => 'local',
        ]);
        $operator = $this->userWithPermission(['operations.readiness', 'operations.execute']);

        $this->actingAs($operator)
            ->post(route('operations.decision.store'), ['decision' => 'approved'])
            ->assertRedirect()
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseCount('operations_release_decisions', 0);
    }

    public function test_readiness_only_operator_cannot_execute_operational_actions(): void
    {
        config(['operations.release.sha' => str_repeat('b', 40)]);
        $operator = $this->userWithPermission('operations.readiness');

        $this->actingAs($operator)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Esta sessão possui acesso de consulta.');

        $this->post(route('operations.runtime-probes.prepare'))->assertForbidden();
        $this->post(route('operations.decision.store'), ['decision' => 'held'])->assertForbidden();
    }

    public function test_operator_can_review_a_safe_unified_history_scoped_by_clinic_and_release(): void
    {
        $sha = str_repeat('7', 40);
        $oldSha = str_repeat('6', 40);
        config(['operations.release.sha' => $sha]);
        $clinicA = Clinic::query()->create([
            'corporate_name' => 'Clínica Histórico A',
            'trade_name' => 'Clínica Histórico A',
            'cnpj' => '00000000000921',
            'active' => true,
        ]);
        $clinicB = Clinic::query()->create([
            'corporate_name' => 'Clínica Histórico B',
            'trade_name' => 'Clínica Histórico B',
            'cnpj' => '00000000000922',
            'active' => true,
        ]);
        $operator = $this->userWithPermission('operations.readiness', $clinicA);
        $now = now();

        OperationsRuntimeProbeEvent::query()->create([
            'clinic_id' => $clinicA->id,
            'actor_user_id' => $operator->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'probe_id' => '01K3PROBE00000000000000001',
            'event' => 'verified',
            'queue_connection' => 'database',
            'queue_mode' => 'worker',
            'storage_disk' => 'local',
            'detail' => 'Quatro verificações seguras aprovadas.',
            'occurred_at' => $now->copy()->subMinute(),
        ]);
        OperationsBackupEvidenceEvent::query()->create([
            'clinic_id' => $clinicA->id,
            'actor_user_id' => $operator->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'backup_identifier' => 'restore-current-safe',
            'status' => 'passed',
            'checks_count' => 3,
            'evidence_sha256' => str_repeat('a', 64),
            'verified_at' => $now->copy()->subMinutes(2),
            'occurred_at' => $now->copy()->subMinutes(2),
        ]);
        OperationsSmokeCheck::query()->create([
            'clinic_id' => $clinicA->id,
            'actor_user_id' => $operator->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'check_key' => 'health_endpoint',
            'completed' => true,
            'note' => 'NOTA-PRIVADA-NAO-EXIBIR',
            'created_at' => $now->copy()->subMinutes(3),
            'updated_at' => $now->copy()->subMinutes(3),
        ]);
        OperationsReleaseDecision::query()->create([
            'clinic_id' => $clinicA->id,
            'actor_user_id' => $operator->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'decision' => 'held',
            'evidence_snapshot' => [],
            'evidence_hash' => str_repeat('b', 64),
            'note' => 'JUSTIFICATIVA-PRIVADA-NAO-EXIBIR',
            'decided_at' => $now->copy()->subMinutes(4),
        ]);
        OperationsBackupEvidenceEvent::query()->create([
            'clinic_id' => $clinicA->id,
            'actor_user_id' => $operator->id,
            'environment' => app()->environment(),
            'release_sha' => $oldSha,
            'backup_identifier' => 'restore-old-release',
            'status' => 'passed',
            'checks_count' => 2,
            'evidence_sha256' => str_repeat('c', 64),
            'verified_at' => $now->copy()->subDay(),
            'occurred_at' => $now->copy()->subDay(),
        ]);
        OperationsRuntimeProbeEvent::query()->create([
            'clinic_id' => $clinicB->id,
            'actor_user_id' => $operator->id,
            'environment' => app()->environment(),
            'release_sha' => $sha,
            'probe_id' => '01K3PROBE00000000000000002',
            'event' => 'verified',
            'queue_connection' => 'database',
            'queue_mode' => 'worker',
            'storage_disk' => 'local',
            'detail' => 'EVENTO-DE-OUTRA-CLINICA',
            'occurred_at' => $now,
        ]);

        $this->actingAs($operator)
            ->get(route('operations.history'))
            ->assertOk()
            ->assertSee('Histórico operacional')
            ->assertSeeInOrder([
                'Saúde da aplicação',
                'Quatro verificações seguras aprovadas.',
                'restore-current-safe',
                'Release mantida em espera',
            ])
            ->assertDontSee('restore-old-release')
            ->assertDontSee('EVENTO-DE-OUTRA-CLINICA')
            ->assertDontSee('NOTA-PRIVADA-NAO-EXIBIR')
            ->assertDontSee('JUSTIFICATIVA-PRIVADA-NAO-EXIBIR')
            ->assertDontSee(str_repeat('a', 64))
            ->assertDontSee(str_repeat('b', 64));

        $this->get(route('operations.history', ['release' => 'all', 'type' => 'backup']))
            ->assertOk()
            ->assertSee('restore-current-safe')
            ->assertSee('restore-old-release')
            ->assertDontSee('Quatro verificações seguras aprovadas.')
            ->assertDontSee('EVENTO-DE-OUTRA-CLINICA');
    }

    public function test_console_guides_pending_release_steps_without_executing_them(): void
    {
        Storage::fake('local');
        $this->app->detectEnvironment(fn (): string => 'production');
        $directory = storage_path('framework/testing/operations-guidance-'.Str::uuid());
        config([
            'app.key' => 'base64:MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTIzNDU2Nzg5MDE=',
            'app.debug' => false,
            'app.url' => 'https://vetflow.example',
            'operations.release.sha' => str_repeat('5', 40),
            'operations.backup.evidence_directory' => $directory.'/backup',
            'operations.runtime_probe.evidence_directory' => $directory.'/runtime',
            'operations.queue.mode' => 'worker',
            'queue.default' => 'database',
            'filesystems.default' => 'local',
            'logging.default' => 'single',
        ]);
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Roteiro',
            'trade_name' => 'Clínica Roteiro',
            'cnpj' => '00000000000923',
            'active' => true,
        ]);
        $reader = $this->userWithPermission('operations.readiness', $clinic);
        $executor = $this->userWithPermission(['operations.readiness', 'operations.execute'], $clinic);

        try {
            $this->actingAs($reader)
                ->get(route('operations.index'))
                ->assertOk()
                ->assertSee('Roteiro de liberação')
                ->assertSee('Comprovar fila e armazenamento')
                ->assertSee('Comprovar restauração do backup')
                ->assertSee('Solicite a um operador com permissão de execução')
                ->assertSee('Somente leitura')
                ->assertSee('2 de 6 etapas');

            $this->actingAs($executor)
                ->get(route('operations.index'))
                ->assertOk()
                ->assertSee('Prepare o probe, aguarde a fila e verifique o resultado nesta tela.')
                ->assertSee('Restaure o backup fora do banco ao vivo')
                ->assertSee('Ação disponível');

            $this->assertDatabaseCount('operations_runtime_probe_events', 0);
            $this->assertDatabaseCount('operations_backup_evidence_events', 0);
            $this->assertDatabaseCount('operations_smoke_checks', 0);
            $this->assertDatabaseCount('operations_release_decisions', 0);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_console_and_report_explain_evidence_expiration_without_private_data(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-08-24 12:00:00 UTC');
        $directory = storage_path('framework/testing/operations-validity-'.Str::uuid());
        config([
            'operations.release.sha' => str_repeat('4', 40),
            'operations.backup.evidence_directory' => $directory.'/backup',
            'operations.backup.evidence_max_age_days' => 30,
            'operations.runtime_probe.evidence_directory' => $directory.'/runtime',
            'operations.runtime_probe.evidence_max_age_minutes' => 180,
            'filesystems.default' => 'local',
        ]);
        File::ensureDirectoryExists($directory.'/backup');
        File::ensureDirectoryExists($directory.'/runtime');
        File::put($directory.'/backup/backup-validity-evidence.json', json_encode([
            'backup_identifier' => 'backup-validity',
            'status' => 'passed',
            'verified_at' => now()->subDays(2)->toIso8601String(),
            'checks' => [['name' => 'tables', 'passed' => true]],
        ], JSON_THROW_ON_ERROR));
        $runtimePath = $directory.'/runtime/runtime-validity-evidence.json';
        File::put($runtimePath, json_encode([
            'probe_id' => '01K3PROBE00000000000000003',
            'status' => 'passed',
            'verified_at' => now()->subMinutes(170)->toIso8601String(),
            'checks' => [['name' => 'queue', 'passed' => true]],
        ], JSON_THROW_ON_ERROR));
        $operator = $this->userWithPermission('operations.readiness');

        try {
            $this->actingAs($operator)
                ->get(route('operations.index'))
                ->assertOk()
                ->assertSee('Dentro do prazo')
                ->assertSee('Vence em breve')
                ->assertSee('10 minutos restantes')
                ->assertDontSee($directory);

            $this->get(route('operations.report.json'))
                ->assertOk()
                ->assertJsonPath('evidence_validity.backup.status', 'fresh')
                ->assertJsonPath('evidence_validity.runtime.status', 'expiring')
                ->assertJsonMissingPath('evidence.backup.path')
                ->assertJsonMissingPath('evidence.runtime.path');

            File::put($runtimePath, json_encode([
                'probe_id' => '01K3PROBE00000000000000003',
                'status' => 'passed',
                'verified_at' => now()->subMinutes(181)->toIso8601String(),
                'checks' => [['name' => 'queue', 'passed' => true]],
            ], JSON_THROW_ON_ERROR));

            $this->get(route('operations.index'))
                ->assertOk()
                ->assertSee('Prazo expirado')
                ->assertSee('Gere uma nova evidência.');
        } finally {
            CarbonImmutable::setTestNow();
            File::deleteDirectory($directory);
        }
    }

    public function test_operator_can_import_sanitized_backup_evidence_without_exposing_private_hashes(): void
    {
        $directory = storage_path('framework/testing/operations-backup-'.Str::uuid());
        $sha = str_repeat('9', 40);
        config([
            'operations.release.sha' => $sha,
            'operations.backup.evidence_directory' => $directory,
        ]);
        $operator = $this->userWithPermission(['operations.readiness', 'operations.execute']);
        $payload = json_encode($this->backupEvidence('pilot-restore-20260824'), JSON_THROW_ON_ERROR);

        try {
            $this->actingAs($operator)
                ->post(route('operations.backup-evidence.import'), [
                    'evidence' => UploadedFile::fake()->createWithContent('restore-evidence.json', $payload),
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseHas('operations_backup_evidence_events', [
                'clinic_id' => $operator->clinic_id,
                'environment' => app()->environment(),
                'release_sha' => $sha,
                'backup_identifier' => 'pilot-restore-20260824',
                'status' => 'passed',
                'checks_count' => 2,
            ]);
            $files = File::files($directory);
            $this->assertCount(1, $files);
            $stored = json_decode(File::get($files[0]), true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('unexpected_secret', $stored);
            $this->assertSame(['name', 'passed'], array_keys($stored['checks'][0]));

            $this->get(route('operations.index'))
                ->assertOk()
                ->assertSee('pilot-restore-20260824')
                ->assertSee('Aprovada')
                ->assertDontSee(str_repeat('a', 64))
                ->assertDontSee(str_repeat('b', 64));

            $this->post(route('operations.backup-evidence.import'), [
                'evidence' => UploadedFile::fake()->createWithContent('restore-evidence.json', $payload),
            ])->assertRedirect()->assertSessionHasErrors('backup_evidence');
            $this->assertDatabaseCount('operations_backup_evidence_events', 1);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_invalid_backup_evidence_is_rejected_without_persistence(): void
    {
        $directory = storage_path('framework/testing/operations-backup-invalid-'.Str::uuid());
        config([
            'operations.release.sha' => str_repeat('8', 40),
            'operations.backup.evidence_directory' => $directory,
        ]);
        $operator = $this->userWithPermission(['operations.readiness', 'operations.execute']);

        $this->actingAs($operator)
            ->post(route('operations.backup-evidence.import'), [
                'evidence' => UploadedFile::fake()->createWithContent(
                    'restore-evidence.json',
                    json_encode(['unexpected_secret' => 'nao armazenar'], JSON_THROW_ON_ERROR),
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('backup_evidence');

        $this->assertDatabaseCount('operations_backup_evidence_events', 0);
        $this->assertDirectoryDoesNotExist($directory);
    }

    /** @param string|array<int, string> $slugs */
    private function userWithPermission(string|array $slugs, ?Clinic $clinic = null): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $slugs = (array) $slugs;
        $permissions = Permission::query()->whereIn('slug', $slugs)->get();
        $this->assertCount(count($slugs), $permissions);
        $role = Role::query()->create([
            'name' => 'Operations test',
            'slug' => 'operations-test-'.Str::lower(Str::random(8)),
            'description' => 'Operations test',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach($permissions->pluck('id')->all());
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function backupEvidence(string $identifier): array
    {
        return [
            'version' => 1,
            'backup_identifier' => $identifier,
            'verified_at' => now()->subMinute()->utc()->toIso8601String(),
            'status' => 'passed',
            'manifest_sha256' => str_repeat('a', 64),
            'restore' => [
                'driver' => 'pgsql',
                'fingerprint' => str_repeat('b', 64),
            ],
            'checks' => [
                ['name' => 'Historico de migrations', 'passed' => true, 'expected' => ['secret' => 'discard']],
                ['name' => 'Tabela users', 'passed' => true, 'actual' => ['secret' => 'discard']],
            ],
            'unexpected_secret' => 'discard',
        ];
    }
}
