<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvSessionResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdv_entry_points_open_a_dedicated_tab(): void
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => null]);
        $this->grantPermissions($user, ['dashboard.view', 'sales.manage']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Abrir ponto de venda em nova aba"', false)
            ->assertSee('aria-label="Abrir PDV em nova aba"', false)
            ->assertSee('aria-label="Abrir nova venda no PDV em outra aba"', false)
            ->assertSee('target="_blank" rel="noopener"', false);
    }

    public function test_pdv_exposes_session_and_connectivity_controls(): void
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => null]);
        $this->grantPermissions($user, ['sales.manage']);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-session-heartbeat-url="'.route('sales.pdv.session').'"', false)
            ->assertSee('data-offline-catalog-url="'.route('sales.pdv.offline-catalog').'"', false)
            ->assertSee('data-offline-sync-url="'.route('sales.pdv.offline-sync').'"', false)
            ->assertSee('data-pdv-connection-state', false)
            ->assertSee('data-pdv-connectivity-alert', false)
            ->assertSee('data-pdv-offline-queue', false)
            ->assertSee('data-pdv-offline-dialog', false)
            ->assertSee('data-pdv-close', false)
            ->assertSee('Sair do PDV');
    }

    public function test_authenticated_pdv_heartbeat_refreshes_the_session_without_cache(): void
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => null]);
        $this->grantPermissions($user, ['sales.manage']);

        $response = $this->actingAs($user)->getJson(route('sales.pdv.session'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('session_lifetime_minutes', (int) config('session.lifetime'));

        $this->assertIsInt(session('pdv_last_heartbeat_at'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_guest_cannot_use_the_pdv_heartbeat(): void
    {
        $this->get(route('sales.pdv.session'))
            ->assertRedirect(route('login'));
    }

    private function grantPermissions(User $user, array $permissionSlugs): void
    {
        $role = Role::query()->create([
            'name' => 'PDV resilience test role',
            'slug' => 'pdv-resilience-'.Str::lower(Str::random(8)),
            'description' => 'Role for PDV resilience tests',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Test permission',
                    'group' => 'Tests',
                    'active' => true,
                ]
            );

            $role->permissions()->attach($permission->id);
        }

        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
