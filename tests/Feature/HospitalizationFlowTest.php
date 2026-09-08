<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Hospitalizations\Models\Hospitalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HospitalizationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_registers_and_discharges_a_hospitalization(): void
    {
        $clinic = $this->clinic('Clínica Internação', '00000000001201');
        $patientId = $this->patient($clinic, 'Aurora');
        $user = $this->userForClinic($clinic);

        $this->actingAs($user)
            ->post(route('hospitalizations.store'), [
                'patient_id' => $patientId,
                'status' => 'hospitalized',
                'accommodation' => 'Baia 02',
                'admitted_at' => '2026-08-17 10:00:00',
                'expected_discharge_at' => '2026-08-18 10:00:00',
                'admission_reason' => 'Observação após procedimento.',
                'clinical_notes' => 'Manter o acompanhamento registrado no prontuário.',
            ])
            ->assertRedirect(route('hospitalizations.index'))
            ->assertSessionDoesntHaveErrors();

        $hospitalization = Hospitalization::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $hospitalization->clinic_id);
        $this->assertSame($patientId, (int) $hospitalization->patient_id);
        $this->assertSame($user->id, (int) $hospitalization->admitted_by);
        $this->assertSame('hospitalized', $hospitalization->status);

        $this->get(route('patients.show', $patientId))
            ->assertOk()
            ->assertSee('Internações')
            ->assertSee('Baia 02');

        $this->put(route('hospitalizations.update', $hospitalization->id), [
            'patient_id' => $patientId,
            'status' => 'discharged',
            'accommodation' => 'Baia 02',
            'admitted_at' => '2026-08-17 10:00:00',
            'discharged_at' => '2026-08-18 09:30:00',
            'admission_reason' => 'Observação após procedimento.',
            'clinical_notes' => 'Acompanhamento concluído.',
            'discharge_notes' => 'Alta registrada com orientações no prontuário.',
        ])
            ->assertRedirect(route('hospitalizations.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('hospitalizations', [
            'id' => $hospitalization->id,
            'status' => 'discharged',
            'discharge_notes' => 'Alta registrada com orientações no prontuário.',
        ]);
    }

    public function test_hospitalization_rejects_a_patient_from_another_clinic(): void
    {
        $clinicA = $this->clinic('Clínica A', '00000000001211');
        $clinicB = $this->clinic('Clínica B', '00000000001212');
        $userA = $this->userForClinic($clinicA);
        $patientB = $this->patient($clinicB, 'Paciente B');

        $this->actingAs($userA)
            ->from(route('hospitalizations.create'))
            ->post(route('hospitalizations.store'), [
                'patient_id' => $patientB,
                'status' => 'hospitalized',
                'admitted_at' => '2026-08-17 10:00:00',
                'admission_reason' => 'Tentativa inválida.',
            ])
            ->assertRedirect(route('hospitalizations.create'))
            ->assertSessionHasErrors('patient_id');

        $this->assertDatabaseCount('hospitalizations', 0);
    }

    public function test_hospitalization_rejects_a_medical_record_from_another_patient(): void
    {
        $clinic = $this->clinic('Clínica vínculo', '00000000001221');
        $user = $this->userForClinic($clinic);
        $patientA = $this->patient($clinic, 'Paciente A');
        $patientB = $this->patient($clinic, 'Paciente B');
        $appointmentId = DB::table('appointments')->insertGetId([
            'clinic_id' => $clinic->id,
            'patient_id' => $patientB,
            'title' => 'Consulta do paciente B',
            'scheduled_at' => '2026-08-17 09:00:00',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $medicalRecordId = DB::table('medical_records')->insertGetId([
            'clinic_id' => $clinic->id,
            'patient_id' => $patientB,
            'appointment_id' => $appointmentId,
            'examined_at' => '2026-08-17 09:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('hospitalizations.create'))
            ->post(route('hospitalizations.store'), [
                'patient_id' => $patientA,
                'medical_record_id' => $medicalRecordId,
                'status' => 'hospitalized',
                'admitted_at' => '2026-08-17 10:00:00',
                'admission_reason' => 'Tentativa de vínculo inválido.',
            ])
            ->assertRedirect(route('hospitalizations.create'))
            ->assertSessionHasErrors('medical_record_id');

        $this->assertDatabaseCount('hospitalizations', 0);
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

    private function patient(Clinic $clinic, string $name): int
    {
        return DB::table('patients')->insertGetId([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'species' => 'Canino',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Perfil internação '.Str::random(6),
            'slug' => 'perfil-internacao-'.Str::lower(Str::random(8)),
            'description' => 'Perfil de teste para internações.',
            'system' => false,
            'active' => true,
        ]);
        foreach (['patients.manage', 'hospitalizations.manage'] as $permissionSlug) {
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
