<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\AnimalSpecies;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use App\Modules\Vaccinations\Models\AnimalVaccine;
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

    public function test_clinic_catalog_vaccine_suggests_a_configured_next_dose(): void
    {
        $clinic = $this->clinic('Clínica Protocolo', '00000000000921');
        $tutor = $this->tutor($clinic, 'Tutor Protocolo');
        $canine = AnimalSpecies::query()->where('normalized_name', 'canino')->firstOrFail();
        $patient = $this->patient($clinic, $tutor, 'Paciente Protocolo', $canine);
        $user = $this->userForClinic($clinic, ['vaccinations.manage']);

        $this->actingAs($user)
            ->post(route('vaccine-catalog.store'), [
                'name' => 'Protocolo de teste da clínica',
                'recommended_doses' => 2,
                'recommended_interval_days' => 28,
                'species_ids' => [$canine->id],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $vaccine = AnimalVaccine::query()->where('name', 'Protocolo de teste da clínica')->firstOrFail();

        $this->post(route('vaccinations.store'), [
            'patient_id' => $patient->id,
            'animal_vaccine_id' => $vaccine->id,
            'status' => 'scheduled',
            'scheduled_for' => '2026-08-15',
        ])
            ->assertRedirect(route('vaccinations.index'))
            ->assertSessionDoesntHaveErrors();

        $vaccination = Vaccination::query()->firstOrFail();

        $this->assertSame($vaccine->id, (int) $vaccination->animal_vaccine_id);
        $this->assertSame('Protocolo de teste da clínica', $vaccination->vaccine_name);
        $this->assertSame('2026-09-12', $vaccination->next_due_at?->toDateString());
    }

    public function test_vaccination_rejects_catalog_vaccine_for_another_species(): void
    {
        $clinic = $this->clinic('Clínica Espécie', '00000000000931');
        $tutor = $this->tutor($clinic, 'Tutor Espécie');
        $feline = AnimalSpecies::query()->where('normalized_name', 'felino')->firstOrFail();
        $canine = AnimalSpecies::query()->where('normalized_name', 'canino')->firstOrFail();
        $patient = $this->patient($clinic, $tutor, 'Paciente Felino', $feline);
        $user = $this->userForClinic($clinic, ['vaccinations.manage']);
        $vaccine = AnimalVaccine::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Vacina canina da clínica',
            'normalized_name' => 'vacina canina da clinica',
            'system' => false,
            'active' => true,
        ]);
        $vaccine->species()->attach($canine->id);

        $this->actingAs($user)
            ->from(route('vaccinations.create'))
            ->post(route('vaccinations.store'), [
                'patient_id' => $patient->id,
                'animal_vaccine_id' => $vaccine->id,
                'status' => 'scheduled',
                'scheduled_for' => '2026-08-15',
            ])
            ->assertRedirect(route('vaccinations.create'))
            ->assertSessionHasErrors('animal_vaccine_id');

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

    private function patient(Clinic $clinic, Tutor $tutor, string $name, ?AnimalSpecies $species = null): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'animal_species_id' => $species?->id,
            'species' => $species?->name ?? 'Canino',
        ]);
    }

    private function medicalRecord(Clinic $clinic, Patient $patient, string $diagnosis): MedicalRecord
    {
        return MedicalRecord::query()->create(['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'appointment_id' => \App\Modules\Appointments\Models\Appointment::query()->create(['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'tutor_id' => $patient->tutor_id, 'title' => 'Consulta', 'scheduled_at' => '2026-08-11 10:00:00', 'status' => 'completed'])->id, 'examined_at' => '2026-08-11 10:30:00', 'diagnosis' => $diagnosis]);
    }
}
