<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrativeAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_change_is_recorded_and_visible_to_the_clinic(): void
    {
        $clinic = $this->clinic('Clínica Auditada', '00000000000611');
        $actor = $this->userForClinic($clinic, ['clinic-branding.manage', 'audit.manage']);

        $this->actingAs($actor)->put(route('clinic-branding.update'), [
            'brand_icon_mode' => 'manual',
            'brand_icon_key' => 'feline',
        ])->assertSessionDoesntHaveErrors();

        $event = AuditEvent::query()->where('event', 'clinic.branding.updated')->firstOrFail();

        $this->assertSame($clinic->id, (int) $event->clinic_id);
        $this->assertSame($actor->id, (int) $event->actor_user_id);
        $this->assertSame('automatic', $event->changes['brand_icon_mode']['before']);
        $this->assertSame('manual', $event->changes['brand_icon_mode']['after']);
        $this->assertSame('feline', $event->changes['brand_icon_key']['after']);

        $this->get(route('audit-events.index'))
            ->assertOk()
            ->assertSee('Identidade visual atualizada')
            ->assertSee('Clínica Auditada')
            ->assertSee($actor->name);
    }

    public function test_access_changes_are_audited_without_storing_passwords(): void
    {
        $clinic = $this->clinic('Clínica Acessos', '00000000000612');
        $actor = $this->userForClinic($clinic, ['users.manage', 'audit.manage']);
        $staffRole = Role::query()->create([
            'name' => 'Atendimento auditável',
            'slug' => 'atendimento-auditavel',
            'description' => 'Perfil de teste',
            'system' => true,
            'active' => true,
        ]);

        $this->actingAs($actor)->post(route('access-users.store'), [
            'name' => 'Colaborador Auditável',
            'email' => 'auditavel@vetflow.test',
            'position' => 'Recepção',
            'password' => 'SenhaAuditavel123!',
            'password_confirmation' => 'SenhaAuditavel123!',
            'active' => '1',
            'role_ids' => [$staffRole->id],
        ])->assertSessionDoesntHaveErrors();

        $createdUser = User::query()->where('email', 'auditavel@vetflow.test')->firstOrFail();
        $event = AuditEvent::query()->where('event', 'access.user.created')->firstOrFail();
        $serialized = json_encode([$event->changes, $event->metadata]);

        $this->assertSame($clinic->id, (int) $event->clinic_id);
        $this->assertSame((string) $createdUser->id, $event->subject_id);
        $this->assertSame(['atendimento-auditavel'], $event->changes['roles']['after']);
        $this->assertStringNotContainsString('SenhaAuditavel123!', $serialized);
        $this->assertStringNotContainsString('password', $serialized);

        $this->put(route('access-users.update', $createdUser), [
            'name' => 'Colaborador Auditável',
            'email' => 'auditavel@vetflow.test',
            'position' => 'Coordenação',
            'password' => 'NovaSenhaAuditavel123!',
            'password_confirmation' => 'NovaSenhaAuditavel123!',
            'active' => '1',
            'role_ids' => [$staffRole->id],
        ])->assertSessionDoesntHaveErrors();

        $updated = AuditEvent::query()->where('event', 'access.user.updated')->firstOrFail();
        $updatedSerialized = json_encode([$updated->changes, $updated->metadata]);

        $this->assertSame('Recepção', $updated->changes['position']['before']);
        $this->assertSame('Coordenação', $updated->changes['position']['after']);
        $this->assertTrue($updated->metadata['password_changed']);
        $this->assertStringNotContainsString('NovaSenhaAuditavel123!', $updatedSerialized);
    }

    public function test_audit_history_is_tenant_scoped_and_permission_protected(): void
    {
        $clinicA = $this->clinic('Clínica Auditoria A', '00000000000613');
        $clinicB = $this->clinic('Clínica Auditoria B', '00000000000614');
        $actorA = $this->userForClinic($clinicA, ['clinic-branding.manage']);
        $auditorB = $this->userForClinic($clinicB, ['audit.manage']);
        $unauthorizedB = $this->userForClinic($clinicB, ['dashboard.view']);

        $this->actingAs($actorA)->put(route('clinic-branding.update'), [
            'brand_icon_mode' => 'manual',
            'brand_icon_key' => 'canine',
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($auditorB)
            ->get(route('audit-events.index'))
            ->assertOk()
            ->assertDontSee('Clínica Auditoria A');

        $this->actingAs($unauthorizedB)
            ->get(route('audit-events.index'))
            ->assertForbidden();
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

    /** @param array<int, string> $permissions */
    private function userForClinic(Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Auditoria '.Str::random(6),
            'slug' => 'auditoria-'.Str::lower(Str::random(8)),
            'description' => 'Teste da trilha administrativa',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline($slug),
                    'description' => 'Permissão do teste de auditoria',
                    'group' => 'Administrativo',
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

        return $user;
    }
}
