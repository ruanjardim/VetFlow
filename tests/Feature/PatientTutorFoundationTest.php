<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientTutorFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_can_create_patient_only_for_tutor_from_own_clinic(): void
    {
        $clinic = $this->clinic('Clínica Própria', '12345678000190');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000191');
        $ownTutor = $this->tutor($clinic, 'Tutor Próprio', '52998224725');
        $otherTutor = $this->tutor($otherClinic, 'Tutor Externo', '11144477735');
        $user = $this->authorizedUser($clinic);

        $this->actingAs($user)
            ->post(route('patients.store'), [
                'tutor_id' => $otherTutor->id,
                'name' => 'Paciente indevido',
            ])
            ->assertSessionHasErrors('tutor_id');

        $this->post(route('patients.store'), [
            'tutor_id' => $ownTutor->id,
            'name' => 'Paciente inválido',
            'birth_date' => '2999-12-31',
            'weight' => 0,
        ])->assertSessionHasErrors(['birth_date', 'weight']);

        $this->post(route('patients.store'), [
            'tutor_id' => $ownTutor->id,
            'name' => 'Luna',
            'species' => 'Canino',
        ])->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'tutor_id' => $ownTutor->id,
            'name' => 'Luna',
        ]);
        $this->assertDatabaseMissing('patients', [
            'name' => 'Paciente indevido',
        ]);
        $this->assertDatabaseMissing('patients', [
            'name' => 'Paciente inválido',
        ]);
    }

    public function test_global_user_patient_inherits_selected_tutor_clinic(): void
    {
        $clinic = $this->clinic('Clínica Central', '12345678000192');
        $tutor = $this->tutor($clinic, 'Tutor Central', '52998224725');
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->post(route('patients.store'), [
                'tutor_id' => $tutor->id,
                'name' => 'Thor',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Thor',
        ]);
    }

    public function test_clinic_user_can_update_patient_with_any_species_for_an_own_tutor(): void
    {
        $clinic = $this->clinic('Clínica Própria', '12345678000193');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000194');
        $originalTutor = $this->tutor($clinic, 'Tutor Original', '52998224725');
        $replacementTutor = $this->tutor($clinic, 'Tutor Atualizado', '11144477735');
        $otherTutor = $this->tutor($otherClinic, 'Tutor Externo', '93541134780');
        $user = $this->authorizedUser($clinic);
        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $originalTutor->id,
            'name' => 'Luna',
            'species' => 'Canino',
        ]);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                'tutor_id' => $replacementTutor->id,
                'name' => 'Estrela',
                'species' => 'Equino',
                'breed' => 'Mangalarga',
                'gender' => 'Fêmea',
                'birth_date' => '2020-01-15',
                'weight' => 450.50,
                'notes' => 'Paciente de grande porte.',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
            'tutor_id' => $replacementTutor->id,
            'name' => 'Estrela',
            'species' => 'Equino',
            'breed' => 'Mangalarga',
        ]);

        $this->put(route('patients.update', $patient), [
            'tutor_id' => $otherTutor->id,
            'name' => 'Estrela',
        ])->assertSessionHasErrors('tutor_id');
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

    private function tutor(Clinic $clinic, string $name, string $cpf): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cpf' => $cpf,
            'phone' => '21999990001',
            'active' => true,
        ]);
    }

    private function authorizedUser(?Clinic $clinic = null): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $permission = Permission::query()->create([
            'name' => 'Gerenciar pacientes',
            'slug' => 'patients.manage',
            'description' => 'Gerenciar pacientes',
            'group' => 'Tests',
            'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Pacientes '.Str::random(6),
            'slug' => 'patients-'.Str::lower(Str::random(8)),
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
