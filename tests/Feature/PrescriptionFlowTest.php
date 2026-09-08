<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use App\Modules\Prescriptions\Models\Prescription;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrescriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_veterinary_user_creates_finalizes_and_cancels_a_prescription_with_history(): void
    {
        [$clinic, $tutor, $patient, $medicalRecord] = $this->clinicalContext('Principal', '00000000001101');
        $user = $this->userForClinic($clinic, ['prescriptions.manage']);

        $response = $this->actingAs($user)->post(route('prescriptions.store'), [
            'medical_record_id' => $medicalRecord->id,
            'prescribed_at' => '2026-08-20 10:30:00',
            'general_instructions' => 'Manter água disponível e observar aceitação.',
            'notes' => 'Retorno se houver piora.',
            'items' => [
                [
                    'medication_name' => 'Medicamento Alfa',
                    'concentration' => '50 mg',
                    'dosage' => '1 comprimido',
                    'route' => 'Oral',
                    'frequency' => 'A cada 12 horas',
                    'duration' => '7 dias',
                    'quantity' => '14 comprimidos',
                    'instructions' => 'Administrar após alimentação.',
                ],
                [
                    'medication_name' => 'Solução Beta',
                    'dosage' => '2 mL',
                    'frequency' => 'Uma vez ao dia',
                ],
            ],
        ]);

        $prescription = Prescription::query()->with('items')->firstOrFail();

        $response
            ->assertRedirect(route('prescriptions.show', $prescription->id))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame($clinic->id, (int) $prescription->clinic_id);
        $this->assertSame($patient->id, (int) $prescription->patient_id);
        $this->assertSame($medicalRecord->id, (int) $prescription->medical_record_id);
        $this->assertSame($user->id, (int) $prescription->created_by);
        $this->assertSame('draft', $prescription->status);
        $this->assertCount(2, $prescription->items);

        $this->actingAs($user)
            ->get(route('prescriptions.show', $prescription->id))
            ->assertOk()
            ->assertSee('Medicamento Alfa')
            ->assertSee('Solução Beta')
            ->assertSee('Rascunho sem validade de documento final.');

        $this->actingAs($user)
            ->patch(route('prescriptions.finalize', $prescription->id))
            ->assertRedirect(route('prescriptions.show', $prescription->id));

        $prescription->refresh();
        $this->assertSame('finalized', $prescription->status);
        $this->assertSame($user->id, (int) $prescription->finalized_by);
        $this->assertNotNull($prescription->finalized_at);

        $this->actingAs($user)
            ->from(route('prescriptions.show', $prescription->id))
            ->put(route('prescriptions.update', $prescription->id), [
                'medical_record_id' => $medicalRecord->id,
                'prescribed_at' => '2026-08-20 11:00:00',
                'items' => [[
                    'medication_name' => 'Item indevido',
                    'dosage' => '1 unidade',
                    'frequency' => 'Uma vez',
                ]],
            ])
            ->assertRedirect(route('prescriptions.show', $prescription->id))
            ->assertSessionHasErrors('prescription');

        $this->assertDatabaseMissing('prescription_items', ['medication_name' => 'Item indevido']);

        $this->actingAs($user)
            ->patch(route('prescriptions.cancel', $prescription->id), [
                'cancellation_reason' => 'Substituída após reavaliação clínica.',
            ])
            ->assertRedirect(route('prescriptions.show', $prescription->id));

        $prescription->refresh();
        $this->assertSame('cancelled', $prescription->status);
        $this->assertSame($user->id, (int) $prescription->cancelled_by);
        $this->assertSame('Substituída após reavaliação clínica.', $prescription->cancellation_reason);
        $this->assertNotNull($prescription->cancelled_at);
    }

    public function test_prescriptions_enforce_permission_tenant_scope_and_patient_profile_visibility(): void
    {
        [$clinicA, $tutorA, $patientA, $recordA] = $this->clinicalContext('A', '00000000001111');
        [$clinicB, $tutorB, $patientB, $recordB] = $this->clinicalContext('B', '00000000001112');
        $authorizedUser = $this->userForClinic($clinicA, ['prescriptions.manage', 'patients.manage']);
        $unauthorizedUser = $this->userForClinic($clinicA, ['patients.manage']);

        $this->actingAs($authorizedUser)
            ->from(route('prescriptions.create'))
            ->post(route('prescriptions.store'), [
                'medical_record_id' => $recordB->id,
                'prescribed_at' => '2026-08-20 12:00:00',
                'items' => [[
                    'medication_name' => 'Medicamento externo',
                    'dosage' => '1 unidade',
                    'frequency' => 'Uma vez ao dia',
                ]],
            ])
            ->assertRedirect(route('prescriptions.create'))
            ->assertSessionHasErrors('medical_record_id');

        $prescriptionA = Prescription::query()->create([
            'clinic_id' => $clinicA->id,
            'patient_id' => $patientA->id,
            'medical_record_id' => $recordA->id,
            'created_by' => $authorizedUser->id,
            'status' => 'finalized',
            'prescribed_at' => '2026-08-20 13:00:00',
            'finalized_at' => '2026-08-20 13:05:00',
            'finalized_by' => $authorizedUser->id,
        ]);
        $prescriptionA->items()->create([
            'position' => 1,
            'medication_name' => 'Medicamento visível na ficha do paciente',
            'dosage' => '5 mg',
            'frequency' => 'A cada 24 horas',
        ]);
        auth()->logout();
        $prescriptionB = Prescription::withoutGlobalScopes()->create([
            'clinic_id' => $clinicB->id,
            'patient_id' => $patientB->id,
            'medical_record_id' => $recordB->id,
            'status' => 'draft',
            'prescribed_at' => '2026-08-20 14:00:00',
        ]);

        $this->actingAs($authorizedUser)
            ->get(route('prescriptions.show', $prescriptionB->id))
            ->assertNotFound();

        $this->actingAs($unauthorizedUser)
            ->get(route('prescriptions.index'))
            ->assertForbidden();

        $this->actingAs($authorizedUser)
            ->get(route('patients.show', $patientA->id))
            ->assertOk()
            ->assertSee('Prescrições')
            ->assertSee('Medicamento visível na ficha do paciente')
            ->assertDontSee('Medicamento externo');
    }

    /** @return array{Clinic, Tutor, Patient, MedicalRecord} */
    private function clinicalContext(string $suffix, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Prescrição '.$suffix,
            'trade_name' => 'Clínica Prescrição '.$suffix,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
        $tutor = Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Responsável '.$suffix,
            'phone' => '21999990000',
            'active' => true,
        ]);
        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Paciente '.$suffix,
            'species' => 'Canino',
        ]);
        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => 'Consulta '.$suffix,
            'scheduled_at' => '2026-08-20 10:00:00',
            'status' => 'completed',
        ]);
        $medicalRecord = MedicalRecord::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-20 10:15:00',
            'diagnosis' => 'Registro '.$suffix,
        ]);

        return [$clinic, $tutor, $patient, $medicalRecord];
    }

    /** @param array<int, string> $permissionSlugs */
    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Perfil '.Str::random(6),
            'slug' => 'perfil-'.Str::lower(Str::random(8)),
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                ['name' => $permissionSlug, 'group' => 'Tests', 'active' => true]
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
