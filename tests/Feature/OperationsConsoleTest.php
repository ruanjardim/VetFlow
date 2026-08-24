<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
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
        $operatorA = $this->userWithPermission('operations.readiness', $clinicA);
        $operatorB = $this->userWithPermission('operations.readiness', $clinicB);

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

    private function userWithPermission(string $slug, ?Clinic $clinic = null): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $permission = Permission::query()->where('slug', $slug)->firstOrFail();
        $role = Role::query()->create([
            'name' => 'Operations test',
            'slug' => 'operations-test-'.Str::lower(Str::random(8)),
            'description' => 'Operations test',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach($permission->id);
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
