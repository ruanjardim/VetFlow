<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_management_permission_cannot_access_user_administration(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create(['active' => true]);

        $this->actingAs($user)
            ->get(route('access-users.index'))
            ->assertForbidden();
    }

    public function test_authorization_seeder_creates_six_operational_presets(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $this->assertSame(
            [
                'administrador',
                'atendimento',
                'caixa',
                'estoque-compras',
                'financeiro',
                'veterinario',
            ],
            Role::query()
                ->whereNull('clinic_id')
                ->where('system', true)
                ->orderBy('slug')
                ->pluck('slug')
                ->all()
        );

        $admin = Role::query()->where('slug', 'administrador')->firstOrFail();
        $veterinarian = Role::query()->where('slug', 'veterinario')->firstOrFail();
        $cashier = Role::query()->where('slug', 'caixa')->firstOrFail();

        $this->assertTrue($admin->permissions()->where('slug', 'users.manage')->exists());
        $this->assertTrue($veterinarian->permissions()->where('slug', 'appointments.manage')->exists());
        $this->assertFalse($veterinarian->permissions()->where('slug', 'financial.manage')->exists());
        $this->assertTrue($cashier->permissions()->where('slug', 'sales.manage')->exists());
        $this->assertFalse($cashier->permissions()->where('slug', 'inventory.manage')->exists());
    }

    public function test_clinic_administrator_only_sees_users_from_own_clinic(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinicA = $this->clinic('Clinica A', '00000000000101');
        $clinicB = $this->clinic('Clinica B', '00000000000102');
        $actor = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Administrador A',
            'active' => true,
        ]);
        $sameClinicUser = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Colaborador visivel',
            'active' => true,
        ]);
        $otherClinicUser = User::factory()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Colaborador oculto',
            'active' => true,
        ]);
        $this->attachRole($actor, $this->role('administrador'));

        $this->actingAs($actor)
            ->get(route('access-users.create'))
            ->assertOk()
            ->assertSee('Veterinario')
            ->assertSee($clinicA->trade_name)
            ->assertDontSee($clinicB->trade_name);

        $this->actingAs($actor)
            ->get(route('access-users.index'))
            ->assertOk()
            ->assertSee($sameClinicUser->name)
            ->assertDontSee($otherClinicUser->name);

        $this->actingAs($actor)
            ->get(route('access-users.edit', $otherClinicUser->id))
            ->assertNotFound();
    }

    public function test_clinic_administrator_creates_user_in_own_clinic_and_assigns_preset(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinicA = $this->clinic('Clinica A', '00000000000201');
        $clinicB = $this->clinic('Clinica B', '00000000000202');
        $actor = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'active' => true,
        ]);
        $this->attachRole($actor, $this->role('administrador'));
        $veterinarian = $this->role('veterinario');

        $this->actingAs($actor)
            ->post(route('access-users.store'), [
                'clinic_id' => $clinicB->id,
                'name' => 'Veterinaria da Clinica A',
                'email' => 'veterinaria-a@vetflow.test',
                'phone' => '21999990001',
                'position' => 'Veterinaria',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'active' => '1',
                'role_ids' => [$veterinarian->id],
            ])
            ->assertRedirect(route('access-users.index'))
            ->assertSessionHasNoErrors();

        $createdUser = User::query()
            ->where('email', 'veterinaria-a@vetflow.test')
            ->firstOrFail();

        $this->assertSame($clinicA->id, $createdUser->clinic_id);
        $this->assertTrue($createdUser->hasRole('veterinario'));
    }

    public function test_global_administrator_can_choose_users_clinic(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinic = $this->clinic('Clinica Destino', '00000000000301');
        $actor = User::factory()->create([
            'clinic_id' => null,
            'active' => true,
        ]);
        $this->attachRole($actor, $this->role('administrador'));
        $cashier = $this->role('caixa');

        $this->actingAs($actor)
            ->post(route('access-users.store'), [
                'clinic_id' => $clinic->id,
                'name' => 'Caixa da Clinica',
                'email' => 'caixa@vetflow.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'active' => '1',
                'role_ids' => [$cashier->id],
            ])
            ->assertRedirect(route('access-users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'clinic_id' => $clinic->id,
            'email' => 'caixa@vetflow.test',
        ]);
    }

    public function test_only_active_system_presets_can_be_assigned(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinic = $this->clinic('Clinica Segura', '00000000000401');
        $actor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
        ]);
        $this->attachRole($actor, $this->role('administrador'));
        $customRole = Role::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Perfil customizado',
            'slug' => 'perfil-customizado',
            'description' => 'Nao atribuivel nesta entrega.',
            'system' => false,
            'active' => true,
        ]);

        $this->actingAs($actor)
            ->post(route('access-users.store'), [
                'name' => 'Usuario invalido',
                'email' => 'invalido@vetflow.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'active' => '1',
                'role_ids' => [$customRole->id],
            ])
            ->assertSessionHasErrors('role_ids.0');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalido@vetflow.test',
        ]);
    }

    public function test_role_changes_keep_history_and_restore_previous_link(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinic = $this->clinic('Clinica Historico', '00000000000501');
        $actor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
        ]);
        $accessUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
        ]);
        $admin = $this->role('administrador');
        $veterinarian = $this->role('veterinario');
        $cashier = $this->role('caixa');
        $this->attachRole($actor, $admin);
        $this->attachRole($accessUser, $veterinarian);
        $originalPasswordHash = $accessUser->password;

        $this->actingAs($actor)
            ->put(route('access-users.update', $accessUser->id), $this->updatePayload($accessUser, [$cashier->id]))
            ->assertRedirect(route('access-users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($originalPasswordHash, $accessUser->fresh()->password);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $accessUser->id,
            'role_id' => $veterinarian->id,
        ]);
        $this->assertNotNull(
            DB::table('user_roles')
                ->where('user_id', $accessUser->id)
                ->where('role_id', $veterinarian->id)
                ->value('deleted_at')
        );

        $this->actingAs($actor)
            ->put(route('access-users.update', $accessUser->id), $this->updatePayload($accessUser, [$veterinarian->id]))
            ->assertRedirect(route('access-users.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull(
            DB::table('user_roles')
                ->where('user_id', $accessUser->id)
                ->where('role_id', $veterinarian->id)
                ->value('deleted_at')
        );
        $this->assertSame(
            1,
            DB::table('user_roles')
                ->where('user_id', $accessUser->id)
                ->where('role_id', $veterinarian->id)
                ->count()
        );
    }

    public function test_administrator_cannot_remove_own_management_access(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinic = $this->clinic('Clinica Autoprotegida', '00000000000601');
        $actor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
        ]);
        $admin = $this->role('administrador');
        $this->attachRole($actor, $admin);

        $this->actingAs($actor)
            ->put(
                route('access-users.update', $actor->id),
                $this->updatePayload($actor, [$this->role('veterinario')->id])
            )
            ->assertSessionHasErrors('role_ids');

        $this->assertTrue($actor->fresh()->hasPermission('users.manage'));
    }

    private function clinic(string $name, string $cnpj): Clinic
    {
        return Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
    }

    private function role(string $slug): Role
    {
        return Role::query()
            ->whereNull('clinic_id')
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function attachRole(User $user, Role $role): void
    {
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $roleIds
     * @return array<string, mixed>
     */
    private function updatePayload(User $user, array $roleIds): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'active' => '1',
            'role_ids' => $roleIds,
        ];
    }
}
