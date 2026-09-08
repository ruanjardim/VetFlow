<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TutorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsible_registration_persists_complete_existing_fields(): void
    {
        $clinic = $this->clinic('Clínica Responsáveis', '12345678000195');
        $user = $this->authorizedUser($clinic);

        $this->actingAs($user)
            ->post(route('tutores.store'), [
                'name' => 'Mariana Alves',
                'cpf' => '529.982.247-25',
                'rg' => '12.345.678-9',
                'birth_date' => '1990-01-15',
                'gender' => 'Feminino',
                'phone' => '(21) 99999-0001',
                'phone_secondary' => '(21) 98888-0002',
                'email' => 'mariana@example.com',
                'zip_code' => '24000-000',
                'street' => 'Rua das Flores',
                'number' => '100',
                'complement' => 'Sala 2',
                'district' => 'Centro',
                'city' => 'Niterói',
                'state' => 'RJ',
                'notes' => 'Contato pelo telefone principal.',
                'active' => '1',
            ])
            ->assertRedirect(route('tutores.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tutors', [
            'clinic_id' => $clinic->id,
            'name' => 'Mariana Alves',
            'cpf' => '52998224725',
            'street' => 'Rua das Flores',
            'district' => 'Centro',
            'city' => 'Niterói',
            'state' => 'RJ',
        ]);
    }

    public function test_responsible_can_keep_own_cpf_when_updating(): void
    {
        $clinic = $this->clinic('Clínica Edição', '12345678000196');
        $user = $this->authorizedUser($clinic);
        $tutor = Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Mariana Alves',
            'cpf' => '52998224725',
            'phone' => '21999990001',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('tutores.update', $tutor), [
                'name' => 'Mariana Alves Atualizada',
                'cpf' => '529.982.247-25',
                'phone' => '(21) 99999-0001',
                'phone_secondary' => '(21) 98888-0002',
                'zip_code' => '24000-000',
                'street' => 'Rua das Flores',
                'number' => '100',
                'district' => 'Centro',
                'city' => 'Niterói',
                'state' => 'RJ',
                'active' => '1',
            ])
            ->assertRedirect(route('tutores.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tutors', [
            'id' => $tutor->id,
            'name' => 'Mariana Alves Atualizada',
            'cpf' => '52998224725',
            'phone_secondary' => '(21) 98888-0002',
            'street' => 'Rua das Flores',
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

    private function authorizedUser(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $permission = Permission::query()->create([
            'name' => 'Gerenciar responsáveis',
            'slug' => 'tutors.manage',
            'description' => 'Gerenciar responsáveis',
            'group' => 'Tests',
            'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Responsáveis '.Str::random(6),
            'slug' => 'responsaveis-'.Str::lower(Str::random(8)),
            'description' => 'Test role',
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
