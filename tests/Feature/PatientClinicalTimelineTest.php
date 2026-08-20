<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Hospitalizations\Models\Hospitalization;
use App\Modules\MedicalRecords\Models\AnimalExam;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\MedicalRecords\Models\MedicalRecordExam;
use App\Modules\MedicalRecords\Models\MedicalRecordExamResult;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Models\PatientClinicalAlert;
use App\Modules\Patients\Services\PatientClinicalProfileService;
use App\Modules\Prescriptions\Models\Prescription;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Vaccinations\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientClinicalTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_consolidates_permitted_clinical_events_in_reverse_chronological_order(): void
    {
        [$clinic, $patient, $appointment, $medicalRecord] = $this->clinicalContext('Principal', '00000000001501');
        $user = $this->userForClinic($clinic, [
            'patients.manage',
            'appointments.manage',
            'medical-records.manage',
            'prescriptions.manage',
            'vaccinations.manage',
            'hospitalizations.manage',
        ]);
        $this->actingAs($user);

        $medicalRecord->update(['created_by' => $user->id]);
        $exam = AnimalExam::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Hemograma cronológico',
            'normalized_name' => 'hemograma cronologico',
            'category' => 'Laboratório',
            'system' => false,
            'active' => true,
        ]);
        $examRequest = MedicalRecordExam::query()->create([
            'medical_record_id' => $medicalRecord->id,
            'animal_exam_id' => $exam->id,
            'exam_name' => $exam->name,
        ]);
        MedicalRecordExamResult::query()->create([
            'clinic_id' => $clinic->id,
            'medical_record_exam_id' => $examRequest->id,
            'created_by' => $user->id,
            'finalized_by' => $user->id,
            'status' => 'finalized',
            'resulted_at' => '2026-08-20 11:00:00',
            'finalized_at' => '2026-08-20 11:05:00',
            'result_summary' => 'Resultado disponível na linha do tempo.',
        ]);
        $prescription = Prescription::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_record_id' => $medicalRecord->id,
            'created_by' => $user->id,
            'status' => 'finalized',
            'prescribed_at' => '2026-08-20 12:00:00',
            'finalized_at' => '2026-08-20 12:05:00',
            'finalized_by' => $user->id,
        ]);
        $prescription->items()->create([
            'position' => 1,
            'medication_name' => 'Medicação cronológica',
            'dosage' => '1 unidade',
            'frequency' => 'A cada 24 horas',
        ]);
        Vaccination::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'vaccine_name' => 'Vacina cronológica',
            'status' => 'applied',
            'scheduled_for' => '2026-08-20',
            'applied_at' => '2026-08-20 13:00:00',
        ]);
        $hospitalization = Hospitalization::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_record_id' => $medicalRecord->id,
            'admitted_by' => $user->id,
            'status' => 'hospitalized',
            'admitted_at' => '2026-08-20 14:00:00',
            'admission_reason' => 'Admissão visível na linha do tempo.',
        ]);
        $hospitalization->evolutions()->create([
            'clinic_id' => $clinic->id,
            'recorded_by' => $user->id,
            'observed_at' => '2026-08-20 15:00:00',
            'notes' => 'Evolução cronológica observada.',
        ]);
        PatientClinicalAlert::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'status' => 'active',
            'title' => 'Alerta cronológico ativo',
            'details' => 'Alerta factual para a equipe.',
            'created_at' => '2026-08-20 16:00:00',
            'updated_at' => '2026-08-20 16:00:00',
        ]);

        $profile = app(PatientClinicalProfileService::class)->forPatient($patient->id, [
            'appointments' => true,
            'medicalRecords' => true,
            'prescriptions' => true,
            'vaccinations' => true,
            'hospitalizations' => true,
        ]);
        $titles = $profile['clinicalTimeline']->pluck('title')->all();

        $this->assertSame([
            'Alerta cronológico ativo',
            'Evolução da internação #'.$hospitalization->id,
            'Admissão hospitalar #'.$hospitalization->id,
            'Vacina cronológica',
            'Prescrição #'.$prescription->id,
            'Hemograma cronológico',
            'Prontuário #'.$medicalRecord->id,
            $appointment->title,
        ], $titles);

        $this->get(route('patients.show', $patient->id))
            ->assertOk()
            ->assertSee('Linha do tempo clínica')
            ->assertSee('data-clinical-timeline', false)
            ->assertSee('Alerta cronológico ativo')
            ->assertSee('Evolução cronológica observada.')
            ->assertSee('Medicação cronológica')
            ->assertSee(route('exam-results.edit', $examRequest->id), false);
    }

    public function test_timeline_hides_unpermitted_sources_and_other_clinic_events(): void
    {
        [$clinicA, $patientA, $appointmentA, $recordA] = $this->clinicalContext('A', '00000000001511');
        [$clinicB, , $appointmentB] = $this->clinicalContext('B', '00000000001512');
        $user = $this->userForClinic($clinicA, ['patients.manage', 'appointments.manage']);

        $recordA->update(['diagnosis' => 'Diagnóstico restrito da clínica A.']);

        $this->actingAs($user)
            ->get(route('patients.show', $patientA->id))
            ->assertOk()
            ->assertSee('Linha do tempo clínica')
            ->assertSee($appointmentA->title)
            ->assertDontSee('Diagnóstico restrito da clínica A.')
            ->assertDontSee($appointmentB->title)
            ->assertDontSee('Prontuário #'.$recordA->id);

        $profile = app(PatientClinicalProfileService::class)->forPatient($patientA->id, [
            'appointments' => true,
            'medicalRecords' => false,
            'prescriptions' => false,
            'vaccinations' => false,
            'hospitalizations' => false,
        ]);

        $this->assertSame(['Consulta'], $profile['clinicalTimeline']->pluck('category')->all());
        $this->assertSame([$appointmentA->title], $profile['clinicalTimeline']->pluck('title')->all());
    }

    /** @return array{Clinic, Patient, Appointment, MedicalRecord} */
    private function clinicalContext(string $suffix, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Linha do Tempo '.$suffix,
            'trade_name' => 'Clínica Linha do Tempo '.$suffix,
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
            'title' => 'Consulta cronológica '.$suffix,
            'scheduled_at' => '2026-08-20 09:00:00',
            'status' => 'completed',
        ]);
        $medicalRecord = MedicalRecord::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-20 09:30:00',
            'diagnosis' => 'Registro cronológico '.$suffix,
        ]);

        return [$clinic, $patient, $appointment, $medicalRecord];
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
