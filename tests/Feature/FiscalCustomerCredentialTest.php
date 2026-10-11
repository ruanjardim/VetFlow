<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Models\FiscalProviderCredential;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalCustomerCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('fiscal.credentials.driver', 'encrypted_database');
    }

    public function test_clinic_connects_and_rotates_its_own_focus_token_without_exposing_plaintext(): void
    {
        $clinic = $this->clinic('Clínica Dona da Conta', '00000000000711');
        $user = $this->fiscalUser($clinic);
        $firstToken = 'homologation-'.str_repeat('a', 24);

        $this->actingAs($user)->post(route('fiscal.focus-credential.connect'), [
            'focus_token' => $firstToken,
            'focus_token_confirmation' => $firstToken,
            'current_password' => 'password',
            'accept_focus_responsibility' => '1',
        ])->assertRedirect(route('fiscal.index'))->assertSessionDoesntHaveErrors();

        $credential = FiscalProviderCredential::query()->firstOrFail();
        $profile = FiscalProfile::query()->firstOrFail();
        $stored = (string) DB::table('fiscal_provider_credentials')->whereKey($credential->id)->value('token');
        $this->assertNotSame($firstToken, $stored);
        $this->assertSame($firstToken, $credential->token);
        $this->assertSame(FiscalProviderCatalog::FOCUS_NFE, $profile->provider);
        $this->assertSame($credential->reference, data_get($profile->metadata, 'provider_credential_reference'));
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.provider-credential.connected',
        ]);

        $this->put(route('fiscal.update'), [
            'enabled' => '0',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'nfce_enabled' => '0',
            'nfse_enabled' => '0',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(
            $credential->reference,
            data_get($profile->fresh()->metadata, 'provider_credential_reference'),
            'Salvar o perfil não pode apagar a referência administrada pela conexão protegida.'
        );

        $this->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Conta conectada')
            ->assertSee('••••'.substr($firstToken, -4), false)
            ->assertDontSee($firstToken);

        $secondToken = 'homologation-'.str_repeat('b', 24);
        $this->post(route('fiscal.focus-credential.connect'), [
            'focus_token' => $secondToken,
            'focus_token_confirmation' => $secondToken,
            'current_password' => 'password',
            'accept_focus_responsibility' => '1',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('fiscal_provider_credentials', 1);
        $this->assertSame($secondToken, $credential->fresh()->token);
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.provider-credential.rotated',
        ]);
        $auditPayload = DB::table('audit_events')->get(['changes', 'metadata'])->toJson();
        $this->assertStringNotContainsString($firstToken, $auditPayload);
        $this->assertStringNotContainsString($secondToken, $auditPayload);
    }

    public function test_connection_requires_password_confirmation_and_customer_responsibility(): void
    {
        $clinic = $this->clinic('Clínica Confirmação', '00000000000712');
        $user = $this->fiscalUser($clinic);
        $token = 'homologation-'.str_repeat('c', 24);

        $response = $this->actingAs($user)->from(route('fiscal.index'))->post(route('fiscal.focus-credential.connect'), [
            'focus_token' => $token,
            'focus_token_confirmation' => 'different-'.str_repeat('d', 24),
            'current_password' => 'wrong-password',
        ])->assertRedirect(route('fiscal.index'))
            ->assertSessionHasErrors(['focus_token', 'current_password', 'accept_focus_responsibility']);

        $oldInput = $response->getSession()->getOldInput();
        $this->assertArrayNotHasKey('focus_token', $oldInput);
        $this->assertArrayNotHasKey('focus_token_confirmation', $oldInput);

        $this->assertDatabaseCount('fiscal_provider_credentials', 0);
        $this->assertDatabaseCount('fiscal_profiles', 0);
    }

    public function test_disconnection_erases_the_secret_blocks_old_reference_and_is_tenant_isolated(): void
    {
        $clinicA = $this->clinic('Clínica A', '00000000000713');
        $clinicB = $this->clinic('Clínica B', '00000000000714');
        $userA = $this->fiscalUser($clinicA);
        $userB = $this->fiscalUser($clinicB);
        $token = 'homologation-'.str_repeat('e', 24);

        $this->actingAs($userA)->post(route('fiscal.focus-credential.connect'), [
            'focus_token' => $token,
            'focus_token_confirmation' => $token,
            'current_password' => 'password',
            'accept_focus_responsibility' => '1',
        ])->assertSessionDoesntHaveErrors();
        $credential = FiscalProviderCredential::query()->firstOrFail();

        $this->actingAs($userB)->delete(route('fiscal.focus-credential.disconnect'), [
            'clinic_id' => $clinicA->id,
            'current_password' => 'password',
            'confirm_disconnect' => '1',
        ])->assertSessionHasErrors('focus_token');
        $this->assertNull($credential->fresh()->revoked_at);

        $this->actingAs($userA)->delete(route('fiscal.focus-credential.disconnect'), [
            'current_password' => 'password',
            'confirm_disconnect' => '1',
        ])->assertRedirect(route('fiscal.index'))->assertSessionDoesntHaveErrors();

        $credential->refresh();
        $profile = FiscalProfile::query()->firstOrFail();
        $this->assertNull($credential->token);
        $this->assertNull($credential->token_last_four);
        $this->assertNotNull($credential->revoked_at);
        $this->assertNull(data_get($profile->metadata, 'provider_credential_reference'));
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinicA->id,
            'event' => 'fiscal.provider-credential.disconnected',
        ]);
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

    private function fiscalUser(Clinic $clinic): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            [
                'name' => 'Gerenciar módulo fiscal',
                'description' => 'Permissão fiscal de teste.',
                'group' => 'Fiscal',
                'active' => true,
            ]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal '.Str::random(6),
            'slug' => 'fiscal-'.Str::lower(Str::random(8)),
            'description' => 'Perfil fiscal de teste',
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
