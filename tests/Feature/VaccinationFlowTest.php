<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Vaccinations\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaccinationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_registers_a_vaccination_and_updates_its_application(): void
    {
        $clinic = $this->clinic('Clínica Vacina', '00000000000901');
        $tutor = $this->tutor($clinic, 'Tutor Vacina');
        $patient = $this->patient($clinic, $tutor, 'Paciente Vacina');
        $user = $this->userForClinic($clinic, ['vaccinations.manage']);

        $this->actingAs($user)
            ->post(route('vaccinations.store'), [
                'patient_id' => $patient->id,
                'vaccine_name' => 'V10',
                'status' => 'scheduled',
                'scheduled_for' => '2026-08-15',
                'next_due_at' => '2027-08-15',
            ])
            ->assertRedirect(route('vaccinations.index'))
            ->assertSessionDoesntHaveErrors();

        $vaccination = Vaccination::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $vaccination->clinic_id);
        $this->assertSame($patient->id, (int) $vaccination->patient_id);
        $this->assertSame($user->id, (int) $vaccination->created_by);
        $this->assertSame('scheduled', $vaccination->status);

        $this->actingAs($user)
            ->put(route('vaccinations.update', $vaccination->id), [
                'patient_id' => $patient->id,
                'vaccine_name' => 'V10',
                'status' => 'applied',
                'scheduled_for' => '2026-08-15',
                'applied_at' => '2026-08-15 09:30:00',
                'next_due_at' => '2027-08-15',
                'batch_number' => 'LOTE-10',
            ])
            ->assertRedirect(route('vaccinations.index'))
            ->assertSessionDoesntHaveErrors();

        $vaccination->refresh();

        $this->assertSame('applied', $vaccination->status);
        $this->assertSame('LOTE-10', $vaccination->batch_number);
        $this->assertNotNull($vaccination->applied_at);
    }

    public function test_vaccination_rejects_external_references_and_unrelated_medical_records(): void
    {
        $clinicA = $this->clinic('Clínica Vacina A', '00000000000911');
        $clinicB = $this->clinic('Clínica Vacina B', '00000000000912');
        $tutorA = $this->tutor($clinicA, 'Tutor A');
        $patientA = $this->patient($clinicA, $tutorA, 'Paciente A');
        $otherPatientA = $this->patient($clinicA, $tutorA, 'Outro paciente A');
        $recordA = $this->medicalRecord($clinicA, $otherPatientA, 'Registro outro paciente');
        $tutorB = $this->tutor($clinicB, 'Tutor B');
        $patientB = $this->patient($clinicB, $tutorB, 'Paciente B');
        $user = $this->userForClinic($clinicA, ['vaccinations.manage']);

        $this->actingAs($user)
            ->from(route('vaccinations.create'))
            ->post(route('vaccinations.store'), [
                'patient_id' => $patientB->id,
                'vaccine_name' => 'V8',
                'status' => 'scheduled',
                'scheduled_for' => '2026-08-15',
            ])
            ->assertRedirect(route('vaccinations.create'))
            ->assertSessionHasErrors('patient_id');

        $this->actingAs($user)
            ->from(route('vaccinations.create'))
            ->post(route('vaccinations.store'), [
                'patient_id' => $patientA->id,
                'medical_record_id' => $recordA->id,
                'vaccine_name' => 'V8',
                'status' => 'scheduled',
                'scheduled_for' => '2026-08-15',
            ])
            ->assertRedirect(route('vaccinations.create'))
            ->assertSessionHasErrors('medical_record_id');

        $this->assertDatabaseCount('vaccinations', 0);
    }

    private function clinic(string $name, string $cnpj): Clinic
    {
        return Clinic::query()->create(['corporate_name' => $name, 'trade_name' => $name, 'cnpj' => $cnpj, 'active' => true]);
    }

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create(['name' => 'Perfil '.Str::random(6), 'slug' => 'perfil-'.Str::lower(Str::random(8)), 'active' => true]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $permissionSlug], ['name' => $permissionSlug, 'group' => 'Tests', 'active' => true]);
            $role->permissions()->attach($permission->id);
        }

        DB::table('user_roles')->insert(['ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function tutor(Clinic $clinic, string $name): Tutor
    {
        return Tutor::query()->create(['clinic_id' => $clinic->id, 'name' => $name, 'phone' => '21999990000', 'active' => true]);
    }

    private function patient(Clinic $clinic, Tutor $tutor, string $name): Patient
    {
        return Patient::query()->create(['clinic_id' => $clinic->id, 'tutor_id' => $tutor->id, 'name' => $name, 'species' => 'Canino']);
    }

    private function medicalRecord(Clinic $clinic, Patient $patient, string $diagnosis): MedicalRecord
    {
        return MedicalRecord::query()->create(['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'appointment_id' => \App\Modules\Appointments\Models\Appointment::query()->create(['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'tutor_id' => $patient->tutor_id, 'title' => 'Consulta', 'scheduled_at' => '2026-08-11 10:00:00', 'status' => 'completed'])->id, 'examined_at' => '2026-08-11 10:30:00', 'diagnosis' => $diagnosis]);
    }
}
