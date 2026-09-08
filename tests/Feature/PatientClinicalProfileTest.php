<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Vaccinations\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientClinicalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_consolidates_own_clinic_clinical_history(): void
    {
        $clinic = $this->clinic('Clinica Perfil', '12345678000190');
        $user = $this->userForClinic($clinic, [
            'patients.manage',
            'appointments.manage',
            'medical-records.manage',
            'vaccinations.manage',
        ]);
        $patient = $this->patient($clinic, 'Luna');
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $patient->tutor_id,
            'title' => 'Consulta preventiva',
            'scheduled_at' => '2026-08-20 09:00:00',
            'status' => 'completed',
        ]);
        $record = MedicalRecord::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'created_by' => $user->id,
            'examined_at' => '2026-08-20 09:30:00',
            'diagnosis' => 'Paciente saudável.',
        ]);
        Vaccination::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_record_id' => $record->id,
            'created_by' => $user->id,
            'vaccine_name' => 'Múltipla canina',
            'status' => 'applied',
            'scheduled_for' => '2026-08-20',
            'applied_at' => '2026-08-20 09:45:00',
        ]);

        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Ficha de Luna')
            ->assertSee('Consulta preventiva')
            ->assertSee('Paciente saudável.')
            ->assertSee('Múltipla canina');
    }

    public function test_profile_keeps_clinical_sections_hidden_without_their_permissions(): void
    {
        $clinic = $this->clinic('Clinica Restrita', '12345678000191');
        $user = $this->userForClinic($clinic, ['patients.manage']);
        $patient = $this->patient($clinic, 'Thor');

        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Ficha de Thor')
            ->assertDontSee('Consultas')
            ->assertDontSee('Prontuários')
            ->assertDontSee('Carteira de vacinação');
    }

    public function test_profile_rejects_patient_from_another_clinic(): void
    {
        $clinic = $this->clinic('Clinica Própria', '12345678000192');
        $otherClinic = $this->clinic('Clinica Externa', '12345678000193');
        $user = $this->userForClinic($clinic, ['patients.manage']);
        $externalPatient = $this->patient($otherClinic, 'Paciente externo');

        $this->actingAs($user)
            ->get(route('patients.show', $externalPatient))
            ->assertNotFound();
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

    private function patient(Clinic $clinic, string $name): Patient
    {
        $tutor = Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Responsável '.$name,
            'cpf' => '52998224725',
            'phone' => '21999990001',
            'active' => true,
        ]);

        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'species' => 'Canino',
        ]);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Perfil '.Str::random(6),
            'slug' => 'perfil-'.Str::lower(Str::random(8)),
            'description' => 'Perfil para teste da ficha clínica.',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Permissão de teste.',
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

        return $user;
    }
}
