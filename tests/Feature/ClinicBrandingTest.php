<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\AnimalSpecies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_administrator_can_choose_the_sidebar_icon(): void
    {
        $clinic = $this->clinic('Clínica Felina', '00000000000601');
        $user = $this->userForClinic($clinic, ['dashboard.view', 'clinic-branding.manage']);

        $this->actingAs($user)
            ->put(route('clinic-branding.update'), [
                'brand_icon_mode' => 'manual',
                'brand_icon_key' => 'feline',
            ])
            ->assertRedirect(route('clinic-branding.edit'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'brand_icon_mode' => 'manual',
            'brand_icon_key' => 'feline',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-brand-icon="feline"', false)
            ->assertSee('Identidade visual');
    }

    public function test_automatic_icon_uses_single_species_and_falls_back_for_mixed_practice(): void
    {
        $clinic = $this->clinic('Clínica Automática', '00000000000602');
        $user = $this->userForClinic($clinic, ['dashboard.view']);
        $canine = AnimalSpecies::query()->where('normalized_name', 'canino')->firstOrFail();
        $feline = AnimalSpecies::query()->where('normalized_name', 'felino')->firstOrFail();

        $user->animalSpeciesPreferences()->attach($canine->id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-brand-icon="canine"', false);

        $user->animalSpeciesPreferences()->attach($feline->id);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-brand-icon="generic"', false);
    }

    public function test_none_mode_hides_icon_and_user_without_permission_cannot_change_it(): void
    {
        $clinic = $this->clinic('Clínica Sem Ícone', '00000000000603');
        $authorized = $this->userForClinic($clinic, ['dashboard.view', 'clinic-branding.manage']);
        $unauthorized = $this->userForClinic($clinic, ['dashboard.view']);

        $this->actingAs($authorized)->put(route('clinic-branding.update'), [
            'brand_icon_mode' => 'none',
            'brand_icon_key' => 'generic',
        ])->assertSessionDoesntHaveErrors();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-brand-icon=', false);

        $this->actingAs($unauthorized)
            ->put(route('clinic-branding.update'), [
                'brand_icon_mode' => 'manual',
                'brand_icon_key' => 'canine',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'brand_icon_mode' => 'none',
            'brand_icon_key' => 'generic',
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

    /** @param array<int, string> $permissions */
    private function userForClinic(Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Marca '.Str::random(6),
            'slug' => 'marca-'.Str::lower(Str::random(8)),
            'description' => 'Teste de identidade visual',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline($slug),
                    'description' => 'Permissão do teste de identidade visual',
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
