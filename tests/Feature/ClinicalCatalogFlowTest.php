<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\AnimalExam;
use App\Modules\MedicalRecords\Models\AnimalPathology;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\AnimalSpecies;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalCatalogFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_exam_requests_are_recorded_without_replacing_the_diagnosis_or_pathologies(): void
    {
        $clinic = $this->clinic('Clínica Catálogo Clínico', '00000000000901');
        $species = AnimalSpecies::query()->whereNull('clinic_id')->where('name', 'Canino')->firstOrFail();
        $tutor = $this->tutor($clinic, 'Responsável Catálogo');
        $patient = $this->patient($clinic, $tutor, $species, 'Luna');
        $appointment = $this->appointment($clinic, $patient, $tutor, 'Consulta clínica');
        $user = $this->userForClinic($clinic);
        $pathology = AnimalPathology::query()->where('name', 'Dermatite atópica')->firstOrFail();
        $otherPathology = AnimalPathology::query()->where('name', 'Otite externa')->firstOrFail();
        $exam = AnimalExam::query()->where('name', 'Hemograma completo')->firstOrFail();
        $otherExam = AnimalExam::query()->where('name', 'Radiografia')->firstOrFail();

        $this->actingAs($user)
            ->post(route('medical-records.store'), [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'examined_at' => '2026-08-16 10:00:00',
                'diagnosis' => 'Diagnóstico livre preservado.',
                'pathology_ids' => [$pathology->id],
                'exam_ids' => [$exam->id],
            ])
            ->assertRedirect(route('medical-records.index'))
            ->assertSessionDoesntHaveErrors();

        $record = MedicalRecord::query()->firstOrFail();

        $this->assertSame('Diagnóstico livre preservado.', $record->diagnosis);
        $this->assertDatabaseHas('medical_record_pathology', [
            'medical_record_id' => $record->id,
            'animal_pathology_id' => $pathology->id,
        ]);
        $this->assertDatabaseHas('medical_record_exams', [
            'medical_record_id' => $record->id,
            'animal_exam_id' => $exam->id,
            'exam_name' => 'Hemograma completo',
        ]);

        $this->actingAs($user)
            ->put(route('medical-records.update', $record->id), [
                'examined_at' => '2026-08-16 11:00:00',
                'diagnosis' => 'Diagnóstico atualizado.',
                'pathology_ids' => [$otherPathology->id],
                'exam_ids' => [$otherExam->id],
            ])
            ->assertRedirect(route('medical-records.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('medical_record_pathology', [
            'medical_record_id' => $record->id,
            'animal_pathology_id' => $pathology->id,
        ]);
        $this->assertDatabaseHas('medical_record_pathology', [
            'medical_record_id' => $record->id,
            'animal_pathology_id' => $otherPathology->id,
        ]);
        $this->assertDatabaseMissing('medical_record_exams', [
            'medical_record_id' => $record->id,
            'animal_exam_id' => $exam->id,
        ]);
        $this->assertDatabaseHas('medical_record_exams', [
            'medical_record_id' => $record->id,
            'animal_exam_id' => $otherExam->id,
        ]);

        $this->actingAs($user)
            ->get(route('medical-records.show', $record->id))
            ->assertOk()
            ->assertSee('Otite externa')
            ->assertSee('Radiografia')
            ->assertSee('Diagnóstico atualizado.');
    }

    public function test_custom_exam_catalog_is_scoped_to_the_clinic_and_can_be_restricted_to_a_species(): void
    {
        $clinicA = $this->clinic('Clínica Catálogo A', '00000000000911');
        $clinicB = $this->clinic('Clínica Catálogo B', '00000000000912');
        $speciesA = $this->species('Espécie catálogo A');
        $speciesB = $this->species('Espécie catálogo B');
        $userA = $this->userForClinic($clinicA);
        $userB = $this->userForClinic($clinicB);

        $this->actingAs($userA)
            ->post(route('exam-catalog.store'), [
                'name' => 'Exame exclusivo da clínica A',
                'category' => 'Teste',
                'species_ids' => [$speciesA->id],
            ])
            ->assertRedirect(route('exam-catalog.index', ['clinic_id' => $clinicA->id]))
            ->assertSessionDoesntHaveErrors();

        $exam = AnimalExam::query()
            ->where('clinic_id', $clinicA->id)
            ->where('name', 'Exame exclusivo da clínica A')
            ->firstOrFail();

        $this->assertDatabaseHas('animal_exam_species', [
            'animal_exam_id' => $exam->id,
            'animal_species_id' => $speciesA->id,
        ]);

        $this->actingAs($userA)
            ->get(route('exam-catalog.index'))
            ->assertOk()
            ->assertSee('Exame exclusivo da clínica A');

        $this->actingAs($userB)
            ->get(route('exam-catalog.index'))
            ->assertOk()
            ->assertDontSee('Exame exclusivo da clínica A');

        $tutor = $this->tutor($clinicB, 'Responsável B');
        $patient = $this->patient($clinicB, $tutor, $speciesB, 'Paciente B');
        $appointment = $this->appointment($clinicB, $patient, $tutor, 'Consulta B');

        $this->actingAs($userB)
            ->from(route('medical-records.create'))
            ->post(route('medical-records.store'), [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'examined_at' => '2026-08-16 12:00:00',
                'exam_ids' => [$exam->id],
            ])
            ->assertRedirect(route('medical-records.create'))
            ->assertSessionHasErrors('exam_ids');
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

    private function species(string $name): AnimalSpecies
    {
        return AnimalSpecies::query()->create([
            'name' => $name,
            'normalized_name' => Str::of($name)->ascii()->lower()->value(),
            'category' => 'Companhia',
            'system' => true,
            'active' => true,
        ]);
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

    private function patient(Clinic $clinic, Tutor $tutor, AnimalSpecies $species, string $name): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'animal_species_id' => $species->id,
            'species' => $species->name,
        ]);
    }

    private function appointment(Clinic $clinic, Patient $patient, Tutor $tutor, string $title): Appointment
    {
        return Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'tutor_id' => $tutor->id,
            'title' => $title,
            'scheduled_at' => '2026-08-16 09:00:00',
            'status' => 'scheduled',
        ]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Perfil clínico '.Str::random(6),
            'slug' => 'perfil-clinico-'.Str::lower(Str::random(8)),
            'description' => 'Perfil de teste',
            'system' => false,
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'medical-records.manage'],
            ['name' => 'Gerenciar prontuários', 'description' => 'Permissão de teste', 'group' => 'Tests', 'active' => true]
        );

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
