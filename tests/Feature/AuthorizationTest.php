<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_access_protected_module(): void
    {
        $user = User::factory()->create(['active' => true]);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_protected_module(): void
    {
        $user = User::factory()->create(['active' => true]);
        $this->grantPermission($user, 'products.manage');

        $this->assertTrue(Gate::forUser($user)->allows('products.manage'));

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Produtos')
            ->assertDontSee('Financeiro');
    }

    public function test_inactive_role_does_not_authorize_user(): void
    {
        $user = User::factory()->create(['active' => true]);
        $this->grantPermission($user, 'products.manage', roleActive: false);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertForbidden();
    }

    public function test_inactive_permission_does_not_authorize_user(): void
    {
        $user = User::factory()->create(['active' => true]);
        $this->grantPermission($user, 'products.manage', permissionActive: false);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertForbidden();
    }

    public function test_authorization_seeder_creates_default_roles_and_assigns_admin_role(): void
    {
        $user = User::factory()->create(['active' => true]);

        $this->seed(AuthorizationSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'financial.manage',
            'active' => true,
        ]);
        $this->assertDatabaseHas('roles', [
            'slug' => 'administrador',
            'system' => true,
            'active' => true,
        ]);
        $this->assertTrue($user->fresh()->hasPermission('financial.manage'));
    }

    private function grantPermission(
        User $user,
        string $permissionSlug,
        bool $roleActive = true,
        bool $permissionActive = true
    ): void {
        $permission = Permission::query()->create([
            'name' => 'Test permission',
            'slug' => $permissionSlug,
            'description' => 'Test permission',
            'group' => 'Tests',
            'active' => $permissionActive,
        ]);

        $role = Role::query()->create([
            'name' => 'Test role',
            'slug' => 'test-role-'.Str::slug($permissionSlug),
            'description' => 'Test role',
            'system' => false,
            'active' => $roleActive,
        ]);

        $role->permissions()->attach($permission->id);

        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
