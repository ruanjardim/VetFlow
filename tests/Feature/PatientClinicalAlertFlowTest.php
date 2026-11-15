<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Hospitalizations\Models\Hospitalization;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Models\PatientClinicalAlert;
use App\Modules\Prescriptions\Models\Prescription;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientClinicalAlertFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_alert_is_auditable_visible_in_risk_flows_and_resolvable(): void
    {
        [$clinic, $patient, $medicalRecord] = $this->clinicalContext('Principal', '00000000001401');
        $user = $this->userForClinic($clinic, [
            'patients.manage',
            'medical-records.manage',
            'prescriptions.manage',
            'hospitalizations.manage',
        ]);

        $prescription = Prescription::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_record_id' => $medicalRecord->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'prescribed_at' => '2026-08-20 10:00:00',
        ]);
        $prescription->items()->create([
            'position' => 1,
            'medication_name' => 'Medicamento de teste',
            'dosage' => '1 unidade',
            'frequency' => 'A cada 24 horas',
        ]);
        $hospitalization = Hospitalization::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_record_id' => $medicalRecord->id,
            'admitted_by' => $user->id,
            'status' => 'hospitalized',
            'admitted_at' => '2026-08-20 10:30:00',
            'admission_reason' => 'Observação assistencial.',
        ]);

        $this->actingAs($user)
            ->post(route('patient-clinical-alerts.store', $patient->id), [
                'title' => 'Reação documentada a medicamento alfa',
                'details' => 'Tutor relata episódio anterior registrado na ficha de origem.',
            ])
            ->assertRedirect(route('patients.show', $patient->id))
            ->assertSessionDoesntHaveErrors();

        $alert = PatientClinicalAlert::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $alert->clinic_id);
        $this->assertSame($patient->id, (int) $alert->patient_id);
        $this->assertSame($user->id, (int) $alert->created_by);
        $this->assertSame('active', $alert->status);

        foreach ([
            route('patients.show', $patient->id),
            route('medical-records.show', $medicalRecord->id),
            route('prescriptions.show', $prescription->id),
            route('hospitalizations.edit', $hospitalization->id),
        ] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertSee('Alertas clínicos')
                ->assertSee('Reação documentada a medicamento alfa');
        }

        $this->actingAs($user)
            ->patch(route('patient-clinical-alerts.resolve', [$patient->id, $alert->id]), [
                'resolution_notes' => 'Informação revisada e substituída por avaliação documentada no prontuário.',
            ])
            ->assertRedirect(route('patients.show', $patient->id))
            ->assertSessionDoesntHaveErrors();

        $alert->refresh();
        $this->assertSame('resolved', $alert->status);
        $this->assertSame($user->id, (int) $alert->resolved_by);
        $this->assertNotNull($alert->resolved_at);

        $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->assertSee('Informação revisada e substituída')
            ->assertSee('0 ativos');

        $this->actingAs($user)
            ->get(route('prescriptions.show', $prescription->id))
            ->assertOk()
            ->assertDontSee('Reação documentada a medicamento alfa');
    }

    public function test_alerts_enforce_permission_tenant_pairing_and_append_only_routes(): void
    {
        [$clinicA, $patientA] = $this->clinicalContext('A', '00000000001411');
        [$clinicB, $patientB] = $this->clinicalContext('B', '00000000001412');
        $authorizedUser = $this->userForClinic($clinicA, ['medical-records.manage']);
        $unauthorizedUser = $this->userForClinic($clinicA, ['patients.manage']);

        $externalAlert = PatientClinicalAlert::query()->create([
            'clinic_id' => $clinicB->id,
            'patient_id' => $patientB->id,
            'status' => 'active',
            'title' => 'Alerta externo',
        ]);

        $this->actingAs($authorizedUser)
            ->post(route('patient-clinical-alerts.store', $patientB->id), [
                'title' => 'Tentativa externa',
            ])
            ->assertNotFound();

        $this->actingAs($authorizedUser)
            ->patch(route('patient-clinical-alerts.resolve', [$patientA->id, $externalAlert->id]), [
                'resolution_notes' => 'Tentativa de cruzar paciente e alerta.',
            ])
            ->assertNotFound();

        $this->actingAs($unauthorizedUser)
            ->post(route('patient-clinical-alerts.store', $patientA->id), [
                'title' => 'Tentativa sem permissão clínica',
            ])
            ->assertForbidden();

        $this->assertSame('active', $externalAlert->fresh()->status);
        $this->assertFalse(Route::has('patient-clinical-alerts.update'));
        $this->assertFalse(Route::has('patient-clinical-alerts.destroy'));
    }

    /** @return array{Clinic, Patient, MedicalRecord} */
    private function clinicalContext(string $suffix, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Alertas '.$suffix,
            'trade_name' => 'Clínica Alertas '.$suffix,
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
            'scheduled_at' => '2026-08-20 09:00:00',
            'status' => 'completed',
        ]);
        $medicalRecord = MedicalRecord::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-20 09:30:00',
        ]);

        return [$clinic, $patient, $medicalRecord];
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
