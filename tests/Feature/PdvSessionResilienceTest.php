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
            ->assertSee('data-offline-shell-url="'.route('sales.pdv.offline-shell').'"', false)
            ->assertSee('data-service-worker-url="'.asset('pdv-sw.js').'"', false)
            ->assertSee('data-pdv-client-request-id', false)
            ->assertSee('data-pdv-connection-state', false)
            ->assertSee('data-pdv-connectivity-alert', false)
            ->assertSee('data-pdv-offline-queue', false)
            ->assertSee('data-pdv-offline-readiness', false)
            ->assertSee('data-pdv-offline-readiness-label', false)
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
            ->assertJsonPath('operator_id', $user->id)
            ->assertJsonStructure(['csrf_token'])
            ->assertJsonPath('session_lifetime_minutes', (int) config('session.lifetime'));

        $this->assertIsInt(session('pdv_last_heartbeat_at'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_offline_shell_is_sanitized_and_contains_no_session_token_or_operator_name(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => null,
            'name' => 'Operador Não Pode Vazar',
        ]);
        $this->grantPermissions($user, ['sales.manage']);
        $sessionToken = 'csrf-nao-pode-ser-cacheado-123';
        $oldInputSecret = 'observacao-de-cliente-nao-pode-ser-cacheada';

        $response = $this->actingAs($user)
            ->withSession([
                '_token' => $sessionToken,
                '_old_input' => [
                    'notes' => $oldInputSecret,
                    'payments' => [['reference' => $oldInputSecret]],
                ],
            ])
            ->get(route('sales.pdv.offline-shell'));

        $response
            ->assertOk()
            ->assertHeader('X-VetFlow-PDV-Shell', '1')
            ->assertSee('data-pdv-offline-shell="1"', false)
            ->assertSee('name="_token" value=""', false)
            ->assertSee('data-pdv-header-operator', false)
            ->assertDontSee('Operador Não Pode Vazar')
            ->assertDontSee($sessionToken)
            ->assertDontSee($oldInputSecret)
            ->assertDontSee('primary-navigation');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertFileExists(public_path('pdv-sw.js'));
        $worker = (string) file_get_contents(public_path('pdv-sw.js'));
        $this->assertStringContainsString("const OFFLINE_SHELL_PATH = '/sales/pdv/offline-shell';", $worker);
        $this->assertStringContainsString("url.pathname !== '/sales/create'", $worker);
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
