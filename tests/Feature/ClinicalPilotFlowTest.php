<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use App\Modules\Schedules\Models\Schedule;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Vaccinations\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalPilotFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_completes_the_clinical_pilot_flow(): void
    {
        $clinic = $this->clinic('Clinica Piloto', '00000000001001');
        $user = $this->userForClinic($clinic, [
            'tutors.manage',
            'patients.manage',
            'schedules.manage',
            'appointments.manage',
            'medical-records.manage',
            'vaccinations.manage',
        ]);

        $this->actingAs($user)
            ->post(route('tutores.store'), [
                'name' => 'Marina Costa',
                'cpf' => '529.982.247-25',
                'phone' => '21999990001',
                'email' => 'marina@example.test',
                'zip_code' => '24020-080',
                'city' => 'Niteroi',
                'state' => 'RJ',
            ])
            ->assertRedirect(route('tutores.index'))
            ->assertSessionDoesntHaveErrors();

        $responsible = Tutor::query()->firstOrFail();

        $this->post(route('patients.store'), [
            'tutor_id' => $responsible->id,
            'name' => 'Estrela',
            'species' => 'Equino',
            'breed' => 'Pampa',
            'gender' => 'Femea',
            'birth_date' => '2020-01-15',
            'weight' => 450.50,
        ])
            ->assertRedirect(route('patients.index'))
            ->assertSessionDoesntHaveErrors();

        $patient = Patient::query()->firstOrFail();

        $this->post(route('schedules.store'), [
            'patient_id' => $patient->id,
            'tutor_id' => $responsible->id,
            'title' => 'Retorno clinico de Estrela',
            'scheduled_date' => '2026-08-20',
            'scheduled_time' => '09:00',
            'type' => 'Retorno',
            'status' => 'confirmado',
        ])
            ->assertRedirect(route('schedules.index'))
            ->assertSessionDoesntHaveErrors();

        $schedule = Schedule::query()->firstOrFail();

        $this->post(route('appointments.store'), [
            'patient_id' => $patient->id,
            'tutor_id' => $responsible->id,
            'title' => 'Consulta clinica de Estrela',
            'description' => 'Avaliacao de rotina do paciente.',
            'scheduled_at' => '2026-08-20 09:00:00',
            'status' => 'completed',
        ])
            ->assertRedirect(route('appointments.index'))
            ->assertSessionDoesntHaveErrors();

        $appointment = Appointment::query()->firstOrFail();

        $this->post(route('medical-records.store'), [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-20 09:30:00',
            'chief_complaint' => 'Acompanhamento preventivo.',
            'anamnesis' => 'Paciente ativo, sem queixas relatadas.',
            'clinical_findings' => 'Sem alteracoes clinicas relevantes.',
            'diagnosis' => 'Paciente saudavel.',
            'treatment_plan' => 'Manter calendario preventivo atualizado.',
            'weight' => 450.50,
            'temperature' => 37.8,
            'heart_rate' => 40,
            'respiratory_rate' => 14,
            'hydration' => 'Adequada',
        ])
            ->assertRedirect(route('medical-records.index'))
            ->assertSessionDoesntHaveErrors();

        $medicalRecord = MedicalRecord::query()->firstOrFail();

        $this->post(route('vaccinations.store'), [
            'patient_id' => $patient->id,
            'medical_record_id' => $medicalRecord->id,
            'vaccine_name' => 'Influenza equina',
            'manufacturer' => 'Laboratorio teste',
            'batch_number' => 'EQ-2026-08',
            'status' => 'applied',
            'scheduled_for' => '2026-08-20',
            'applied_at' => '2026-08-20 09:45:00',
            'next_due_at' => '2027-08-20',
        ])
            ->assertRedirect(route('vaccinations.index'))
            ->assertSessionDoesntHaveErrors();

        $vaccination = Vaccination::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $responsible->clinic_id);
        $this->assertSame($responsible->id, (int) $patient->tutor_id);
        $this->assertSame($patient->id, (int) $schedule->patient_id);
        $this->assertSame($responsible->id, (int) $schedule->tutor_id);
        $this->assertSame($patient->id, (int) $appointment->patient_id);
        $this->assertSame($responsible->id, (int) $appointment->tutor_id);
        $this->assertSame($appointment->id, (int) $medicalRecord->appointment_id);
        $this->assertSame($patient->id, (int) $medicalRecord->patient_id);
        $this->assertSame($medicalRecord->id, (int) $vaccination->medical_record_id);
        $this->assertSame($patient->id, (int) $vaccination->patient_id);
        $this->assertSame('applied', $vaccination->status);
        $this->assertSame($user->id, (int) $vaccination->created_by);
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

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);

        $role = Role::query()->create([
            'name' => 'Perfil piloto '.Str::random(6),
            'slug' => 'perfil-piloto-'.Str::lower(Str::random(8)),
            'description' => 'Perfil para validar o fluxo clinico completo.',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Permissao de teste',
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
