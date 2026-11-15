<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\AnimalExam;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\MedicalRecords\Models\MedicalRecordExam;
use App\Modules\MedicalRecords\Models\MedicalRecordExamResult;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamResultFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_records_finalizes_and_cancels_an_exam_result(): void
    {
        [$clinic, $medicalRecord, $examRequest] = $this->clinicalContext('Principal', '00000000001201');
        $user = $this->userForClinic($clinic, true);

        $this->actingAs($user)
            ->from(route('exam-results.edit', $examRequest->id))
            ->patch(route('exam-results.finalize', $examRequest->id))
            ->assertRedirect(route('exam-results.edit', $examRequest->id))
            ->assertSessionHasErrors('result');

        $this->actingAs($user)
            ->put(route('exam-results.save', $examRequest->id), [
                'collected_at' => '2026-08-20 08:30:00',
                'resulted_at' => '2026-08-20 11:00:00',
                'laboratory_name' => 'Laboratório VetFlow',
                'result_summary' => 'Resultado sem alterações relevantes.',
                'result_details' => "Parâmetro A: 10 unidades\nParâmetro B: 20 unidades",
                'reference_notes' => 'Referências reproduzidas conforme documento de origem.',
                'notes' => 'Conferido antes da finalização.',
            ])
            ->assertRedirect(route('exam-results.edit', $examRequest->id))
            ->assertSessionDoesntHaveErrors();

        $result = MedicalRecordExamResult::query()->firstOrFail();

        $this->assertSame($clinic->id, (int) $result->clinic_id);
        $this->assertSame($examRequest->id, (int) $result->medical_record_exam_id);
        $this->assertSame($user->id, (int) $result->created_by);
        $this->assertSame('draft', $result->status);

        $this->actingAs($user)
            ->get(route('exam-results.edit', $examRequest->id))
            ->assertOk()
            ->assertSee('Laboratório VetFlow')
            ->assertSee('Resultado sem alterações relevantes.');

        $this->actingAs($user)
            ->patch(route('exam-results.finalize', $examRequest->id))
            ->assertRedirect(route('exam-results.edit', $examRequest->id));

        $result->refresh();
        $this->assertSame('finalized', $result->status);
        $this->assertSame($user->id, (int) $result->finalized_by);
        $this->assertNotNull($result->finalized_at);

        $this->actingAs($user)
            ->from(route('exam-results.edit', $examRequest->id))
            ->put(route('exam-results.save', $examRequest->id), [
                'result_summary' => 'Alteração indevida.',
            ])
            ->assertRedirect(route('exam-results.edit', $examRequest->id))
            ->assertSessionHasErrors('result');

        $this->assertSame(
            'Resultado sem alterações relevantes.',
            $result->fresh()->result_summary
        );

        $this->actingAs($user)
            ->patch(route('exam-results.cancel', $examRequest->id), [
                'cancellation_reason' => 'Documento de origem substituído pelo laboratório.',
            ])
            ->assertRedirect(route('exam-results.edit', $examRequest->id));

        $result->refresh();
        $this->assertSame('cancelled', $result->status);
        $this->assertSame($user->id, (int) $result->cancelled_by);
        $this->assertNotNull($result->cancelled_at);

        $this->actingAs($user)
            ->get(route('medical-records.show', $medicalRecord->id))
            ->assertOk()
            ->assertSee('Resultado sem alterações relevantes.')
            ->assertSee('Cancelado');
    }

    public function test_exam_results_enforce_permission_tenant_scope_and_protect_the_source_request(): void
    {
        [$clinicA, $recordA, $requestA] = $this->clinicalContext('A', '00000000001211');
        [$clinicB, $recordB, $requestB] = $this->clinicalContext('B', '00000000001212');
        $authorizedUser = $this->userForClinic($clinicA, true);
        $unauthorizedUser = $this->userForClinic($clinicA, false);

        $this->actingAs($authorizedUser)
            ->get(route('exam-results.edit', $requestB->id))
            ->assertNotFound();

        $this->actingAs($unauthorizedUser)
            ->get(route('exam-results.edit', $requestA->id))
            ->assertForbidden();

        $this->actingAs($authorizedUser)
            ->put(route('exam-results.save', $requestA->id), [
                'result_summary' => 'Resultado protegido no histórico.',
            ])
            ->assertRedirect(route('exam-results.edit', $requestA->id));

        $this->actingAs($authorizedUser)
            ->from(route('medical-records.edit', $recordA->id))
            ->put(route('medical-records.update', $recordA->id), [
                'examined_at' => '2026-08-20 10:15:00',
                'exam_ids' => [],
            ])
            ->assertRedirect(route('medical-records.edit', $recordA->id))
            ->assertSessionHasErrors('exam_ids');

        $this->assertDatabaseHas('medical_record_exams', ['id' => $requestA->id]);
        $this->assertDatabaseHas('medical_record_exam_results', [
            'medical_record_exam_id' => $requestA->id,
            'result_summary' => 'Resultado protegido no histórico.',
        ]);
        $this->assertDatabaseMissing('medical_record_exam_results', [
            'medical_record_exam_id' => $requestB->id,
        ]);

        $this->assertSame($recordB->id, (int) $requestB->medical_record_id);
    }

    /** @return array{Clinic, MedicalRecord, MedicalRecordExam} */
    private function clinicalContext(string $suffix, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Resultado '.$suffix,
            'trade_name' => 'Clínica Resultado '.$suffix,
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
        $exam = AnimalExam::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Hemograma '.$suffix,
            'normalized_name' => 'hemograma '.Str::lower($suffix),
            'category' => 'Laboratório',
            'system' => false,
            'active' => true,
        ]);
        $examRequest = MedicalRecordExam::query()->create([
            'medical_record_id' => $medicalRecord->id,
            'animal_exam_id' => $exam->id,
            'exam_name' => $exam->name,
        ]);

        return [$clinic, $medicalRecord, $examRequest];
    }

    private function userForClinic(Clinic $clinic, bool $authorized): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Perfil '.Str::random(6),
            'slug' => 'perfil-'.Str::lower(Str::random(8)),
            'active' => true,
        ]);

        if ($authorized) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => 'medical-records.manage'],
                ['name' => 'Gerenciar prontuários', 'group' => 'Tests', 'active' => true]
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
