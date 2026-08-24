<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function userWithPermission(string $slug): User
    {
        $user = User::factory()->create(['active' => true]);
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
