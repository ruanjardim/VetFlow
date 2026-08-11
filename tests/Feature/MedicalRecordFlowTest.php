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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicalRecordFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_creates_and_updates_a_medical_record_for_its_appointment(): void
    {
        $clinic = $this->clinic('Clínica Prontuário', '00000000000801');
        $tutor = $this->tutor($clinic, 'Ana Tutor');
        $patient = $this->patient($clinic, $tutor, 'Luna');
        $appointment = $this->appointment($clinic, $patient, $tutor, 'Consulta clínica');
        $user = $this->userForClinic($clinic, ['medical-records.manage']);

        $response = $this->actingAs($user)->post(route('medical-records.store'), [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-11 10:30:00',
            'chief_complaint' => 'Apatia e redução de apetite.',
            'anamnesis' => 'Sintomas iniciados há dois dias.',
            'clinical_findings' => 'Sem alterações respiratórias.',
            'diagnosis' => 'Quadro gastrointestinal leve.',
            'treatment_plan' => 'Observação e retorno em 48 horas.',
            'prescription_notes' => 'Orientações registradas durante o atendimento.',
            'weight' => '12.40',
            'temperature' => '38.7',
            'heart_rate' => 110,
            'respiratory_rate' => 28,
            'hydration' => 'Adequada',
            'notes' => 'Tutor orientado sobre sinais de alerta.',
        ]);

        $response
            ->assertRedirect(route('medical-records.index'))
            ->assertSessionDoesntHaveErrors();

        $medicalRecord = MedicalRecord::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $medicalRecord->clinic_id);
        $this->assertSame($patient->id, (int) $medicalRecord->patient_id);
        $this->assertSame($appointment->id, (int) $medicalRecord->appointment_id);
        $this->assertSame($user->id, (int) $medicalRecord->created_by);
        $this->assertSame('Quadro gastrointestinal leve.', $medicalRecord->diagnosis);

        $this->actingAs($user)
            ->get(route('medical-records.show', $medicalRecord->id))
            ->assertOk()
            ->assertSee('Quadro gastrointestinal leve.')
            ->assertSee('Luna');

        $this->actingAs($user)
            ->put(route('medical-records.update', $medicalRecord->id), [
                'patient_id' => 999999,
                'appointment_id' => 999999,
                'examined_at' => '2026-08-11 11:00:00',
                'diagnosis' => 'Quadro gastrointestinal em acompanhamento.',
                'weight' => '12.50',
            ])
            ->assertRedirect(route('medical-records.index'))
            ->assertSessionDoesntHaveErrors();

        $medicalRecord->refresh();

        $this->assertSame($patient->id, (int) $medicalRecord->patient_id);
        $this->assertSame($appointment->id, (int) $medicalRecord->appointment_id);
        $this->assertSame('Quadro gastrointestinal em acompanhamento.', $medicalRecord->diagnosis);
        $this->assertEquals(12.50, (float) $medicalRecord->weight);
    }

    public function test_medical_record_rejects_external_or_mismatched_patient_and_appointment(): void
    {
        $clinicA = $this->clinic('Clínica Registro A', '00000000000811');
        $clinicB = $this->clinic('Clínica Registro B', '00000000000812');
        $tutorA = $this->tutor($clinicA, 'Tutor A');
        $patientA = $this->patient($clinicA, $tutorA, 'Paciente A');
        $otherPatientA = $this->patient($clinicA, $tutorA, 'Outro paciente A');
        $appointmentA = $this->appointment($clinicA, $patientA, $tutorA, 'Consulta A');
        $tutorB = $this->tutor($clinicB, 'Tutor B');
        $patientB = $this->patient($clinicB, $tutorB, 'Paciente B');
        $appointmentB = $this->appointment($clinicB, $patientB, $tutorB, 'Consulta B');
        $user = $this->userForClinic($clinicA, ['medical-records.manage']);

        $this->actingAs($user)
            ->from(route('medical-records.create'))
            ->post(route('medical-records.store'), [
                'patient_id' => $patientB->id,
                'appointment_id' => $appointmentB->id,
                'examined_at' => '2026-08-11 10:30:00',
            ])
            ->assertRedirect(route('medical-records.create'))
            ->assertSessionHasErrors(['patient_id', 'appointment_id']);

        $this->actingAs($user)
            ->from(route('medical-records.create'))
            ->post(route('medical-records.store'), [
                'patient_id' => $otherPatientA->id,
                'appointment_id' => $appointmentA->id,
                'examined_at' => '2026-08-11 10:30:00',
            ])
            ->assertRedirect(route('medical-records.create'))
            ->assertSessionHasErrors('patient_id');

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_medical_records_are_scoped_to_the_authenticated_clinic_and_permission(): void
    {
        $clinicA = $this->clinic('Clínica Escopo A', '00000000000821');
        $clinicB = $this->clinic('Clínica Escopo B', '00000000000822');
        $tutorA = $this->tutor($clinicA, 'Tutor Escopo A');
        $patientA = $this->patient($clinicA, $tutorA, 'Paciente Escopo A');
        $appointmentA = $this->appointment($clinicA, $patientA, $tutorA, 'Consulta Escopo A');
        $tutorB = $this->tutor($clinicB, 'Tutor Escopo B');
        $patientB = $this->patient($clinicB, $tutorB, 'Paciente Escopo B');
        $appointmentB = $this->appointment($clinicB, $patientB, $tutorB, 'Consulta Escopo B');

        $recordA = $this->medicalRecord($clinicA, $patientA, $appointmentA, 'Diagnóstico A');
        $recordB = $this->medicalRecord($clinicB, $patientB, $appointmentB, 'Diagnóstico B');
        $authorizedUser = $this->userForClinic($clinicA, ['medical-records.manage']);
        $unauthorizedUser = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinicA->id,
        ]);

        $this->actingAs($authorizedUser)
            ->get(route('medical-records.index'))
            ->assertOk()
            ->assertSee('Paciente Escopo A')
            ->assertDontSee('Paciente Escopo B');

        $this->actingAs($authorizedUser)
            ->get(route('medical-records.show', $recordB->id))
            ->assertNotFound();

        $this->actingAs($unauthorizedUser)
            ->get(route('medical-records.index'))
            ->assertForbidden();

        $this->assertSame($recordA->id, MedicalRecord::withoutGlobalScopes()->where('clinic_id', $clinicA->id)->value('id'));
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
            'name' => 'Perfil de teste '.Str::random(6),
            'slug' => 'perfil-teste-'.Str::lower(Str::random(8)),
            'description' => 'Perfil de teste',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Permissão de teste',
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

    private function tutor(Clinic $clinic, string $name): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'phone' => '21999990000',
            'active' => true,
        ]);
    }

    private function patient(Clinic $clinic, Tutor $tutor, string $name): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'species' => 'Canino',
        ]);
    }

    private function appointment(Clinic $clinic, Patient $patient, Tutor $tutor, string $title): Appointment
    {
        return Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => $title,
            'scheduled_at' => '2026-08-11 10:00:00',
            'status' => 'scheduled',
        ]);
    }

    private function medicalRecord(Clinic $clinic, Patient $patient, Appointment $appointment, string $diagnosis): MedicalRecord
    {
        return MedicalRecord::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-11 10:30:00',
            'diagnosis' => $diagnosis,
        ]);
    }
}
