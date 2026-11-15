<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\MedicalRecords\Models\AnimalPathology;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\AnimalSpecies;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PathologyCatalogFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_catalog_is_alphabetical_searchable_and_filtered_by_species(): void
    {
        $clinic = $this->clinic('Clínica Patologias', '00000000000901');
        $user = $this->userForClinic($clinic, ['medical-records.manage']);
        $canine = AnimalSpecies::query()->where('name', 'Canino')->firstOrFail();

        $response = $this->actingAs($user)
            ->get(route('pathology-catalog.index', ['species_id' => $canine->id]))
            ->assertOk()
            ->assertSee('Patologias')
            ->assertSee('data-catalog-search', false)
            ->assertSee('data-auto-submit-select', false)
            ->assertSee('data-history-back', false)
            ->assertSee('Cinomose canina')
            ->assertSee('Raiva')
            ->assertDontSee('Cardiomiopatia hipertrófica felina');

        $this->assertStringContainsString(
            'Alergia alimentar',
            $response->getContent()
        );
        $this->assertTrue(
            strpos($response->getContent(), 'Alergia alimentar')
                < strpos($response->getContent(), 'Cinomose canina')
        );
    }

    public function test_custom_pathology_is_reusable_and_isolated_by_clinic(): void
    {
        $clinicA = $this->clinic('Clínica Patologia A', '00000000000902');
        $clinicB = $this->clinic('Clínica Patologia B', '00000000000903');
        $userA = $this->userForClinic($clinicA, ['medical-records.manage']);
        $userB = $this->userForClinic($clinicB, ['medical-records.manage']);
        $feline = AnimalSpecies::query()->where('name', 'Felino')->firstOrFail();

        $this->actingAs($userA)->post(route('pathology-catalog.store'), [
            'name' => 'Síndrome exclusiva A',
            'species_ids' => [$feline->id],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $pathology = AnimalPathology::query()
            ->where('clinic_id', $clinicA->id)
            ->where('name', 'Síndrome exclusiva A')
            ->firstOrFail();

        $this->assertFalse($pathology->system);
        $this->assertSame([$feline->id], $pathology->species()->pluck('animal_species.id')->all());

        $this->actingAs($userA)
            ->get(route('pathology-catalog.index', ['species_id' => $feline->id]))
            ->assertOk()
            ->assertSee('Síndrome exclusiva A');

        $this->actingAs($userB)
            ->get(route('pathology-catalog.index', ['species_id' => $feline->id]))
            ->assertOk()
            ->assertDontSee('Síndrome exclusiva A');
    }

    public function test_medical_record_keeps_free_diagnosis_and_links_structured_pathologies(): void
    {
        $clinic = $this->clinic('Clínica Prontuário Estruturado', '00000000000904');
        $user = $this->userForClinic($clinic, ['medical-records.manage']);
        $canine = AnimalSpecies::query()->where('name', 'Canino')->firstOrFail();
        $tutor = $this->tutor($clinic, 'Tutor Luna');
        $patient = $this->patient($clinic, $tutor, $canine, 'Luna');
        $appointment = $this->appointment($clinic, $tutor, $patient);
        $cinomose = AnimalPathology::query()->where('name', 'Cinomose canina')->firstOrFail();
        $felineOnly = AnimalPathology::query()->where('name', 'Cardiomiopatia hipertrófica felina')->firstOrFail();

        $this->actingAs($user)
            ->get(route('medical-records.create', [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
            ]))
            ->assertOk()
            ->assertSee('Patologias padronizadas')
            ->assertSee('Cinomose canina')
            ->assertSee('Outra patologia')
            ->assertSee('data-species-ids="'.$canine->id.'"', false);

        $this->post(route('medical-records.store'), [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-13 10:00:00',
            'diagnosis' => 'Hipótese clínica preservada em texto livre.',
            'pathology_ids' => [$cinomose->id],
            'new_pathology' => 'Condição clínica própria',
        ])->assertRedirect(route('medical-records.index'))->assertSessionDoesntHaveErrors();

        $record = MedicalRecord::query()->firstOrFail();
        $custom = AnimalPathology::query()->where('clinic_id', $clinic->id)->where('name', 'Condição clínica própria')->firstOrFail();

        $this->assertSame('Hipótese clínica preservada em texto livre.', $record->diagnosis);
        $this->assertEqualsCanonicalizing(
            [$cinomose->id, $custom->id],
            $record->pathologies()->pluck('animal_pathologies.id')->all()
        );
        $this->assertSame([$canine->id], $custom->species()->pluck('animal_species.id')->all());

        $this->get(route('medical-records.show', $record->id))
            ->assertOk()
            ->assertSee('Hipótese clínica preservada em texto livre.')
            ->assertSee('Cinomose canina')
            ->assertSee('Condição clínica própria');

        $this->put(route('medical-records.update', $record->id), [
            'examined_at' => '2026-08-13 11:00:00',
            'diagnosis' => 'Texto livre atualizado.',
            'pathology_ids' => [$custom->id],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame([$custom->id], $record->refresh()->pathologies()->pluck('animal_pathologies.id')->all());

        $this->put(route('medical-records.update', $record->id), [
            'examined_at' => '2026-08-13 12:00:00',
            'pathology_ids' => [$felineOnly->id],
        ])->assertSessionHasErrors('pathology_ids');
    }

    public function test_medical_record_rejects_pathology_owned_by_another_clinic(): void
    {
        $clinicA = $this->clinic('Clínica Vínculo A', '00000000000905');
        $clinicB = $this->clinic('Clínica Vínculo B', '00000000000906');
        $userA = $this->userForClinic($clinicA, ['medical-records.manage']);
        $userB = $this->userForClinic($clinicB, ['medical-records.manage']);
        $canine = AnimalSpecies::query()->where('name', 'Canino')->firstOrFail();

        $this->actingAs($userA)->post(route('pathology-catalog.store'), [
            'name' => 'Diagnóstico privado A',
            'species_ids' => [$canine->id],
        ])->assertSessionDoesntHaveErrors();
        $private = AnimalPathology::query()->where('clinic_id', $clinicA->id)->firstOrFail();

        $this->actingAs($userB);
        $tutor = $this->tutor($clinicB, 'Tutor B');
        $patient = $this->patient($clinicB, $tutor, $canine, 'Paciente B');
        $appointment = $this->appointment($clinicB, $tutor, $patient);

        $this->post(route('medical-records.store'), [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'examined_at' => '2026-08-13 10:00:00',
            'pathology_ids' => [$private->id],
        ])->assertSessionHasErrors('pathology_ids');

        $this->assertDatabaseCount('medical_records', 0);
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
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $role = Role::query()->create([
            'name' => 'Patologias '.Str::random(6),
            'slug' => 'patologias-'.Str::lower(Str::random(8)),
            'description' => 'Teste de patologias',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::headline($slug), 'description' => 'Teste', 'group' => 'Tests', 'active' => true]
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

    private function patient(Clinic $clinic, Tutor $tutor, AnimalSpecies $species, string $name): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'species' => $species->name,
            'animal_species_id' => $species->id,
        ]);
    }

    private function appointment(Clinic $clinic, Tutor $tutor, Patient $patient): Appointment
    {
        return Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'patient_id' => $patient->id,
            'title' => 'Consulta de patologias',
            'scheduled_at' => '2026-08-13 09:30:00',
            'status' => 'scheduled',
        ]);
    }
}
