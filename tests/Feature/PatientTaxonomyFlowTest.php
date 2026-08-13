<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\AnimalBreed;
use App\Modules\Patients\Models\AnimalSpecies;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientTaxonomyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_catalog_supports_exotic_species_and_filters_breeds_by_species(): void
    {
        $clinic = $this->clinic('Clínica Exóticos', '00000000000401');
        $tutor = $this->tutor($clinic, 'Responsável Exóticos');
        $user = $this->userForClinic($clinic);
        $species = AnimalSpecies::query()->whereNull('clinic_id')->where('name', 'Calopsita')->firstOrFail();
        $breed = AnimalBreed::query()
            ->where('animal_species_id', $species->id)
            ->where('name', 'Lutino')
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Calopsita')
            ->assertSee('Jabuti')
            ->assertSee('Ouriço pigmeu africano')
            ->assertSee('Outra espécie — cadastrar')
            ->assertSee('data-species-id="'.$species->id.'"', false);

        $this->post(route('patients.store'), [
            'tutor_id' => $tutor->id,
            'name' => 'Sol',
            'species_choice' => (string) $species->id,
            'breed_choice' => (string) $breed->id,
        ])->assertRedirect(route('patients.index'))->assertSessionDoesntHaveErrors();

        $patient = Patient::query()->where('name', 'Sol')->firstOrFail();

        $this->assertSame($species->id, (int) $patient->animal_species_id);
        $this->assertSame($breed->id, (int) $patient->animal_breed_id);
        $this->assertSame('Calopsita', $patient->species);
        $this->assertSame('Lutino', $patient->breed);
    }

    public function test_other_option_creates_reusable_clinic_catalog_entries_instead_of_saving_other(): void
    {
        $clinic = $this->clinic('Clínica Catálogo Próprio', '00000000000402');
        $tutor = $this->tutor($clinic, 'Responsável Catálogo');
        $user = $this->userForClinic($clinic);

        $this->actingAs($user)->post(route('patients.store'), [
            'tutor_id' => $tutor->id,
            'name' => 'Lua',
            'species_choice' => 'other',
            'new_species' => 'Tenreque',
            'breed_choice' => 'other',
            'new_breed' => 'Leucístico',
        ])->assertRedirect(route('patients.index'))->assertSessionDoesntHaveErrors();

        $species = AnimalSpecies::query()->where('clinic_id', $clinic->id)->where('name', 'Tenreque')->firstOrFail();
        $breed = AnimalBreed::query()->where('clinic_id', $clinic->id)->where('name', 'Leucístico')->firstOrFail();
        $patient = Patient::query()->where('name', 'Lua')->firstOrFail();

        $this->assertFalse($species->system);
        $this->assertSame($species->id, (int) $breed->animal_species_id);
        $this->assertSame('Tenreque', $patient->species);
        $this->assertSame('Leucístico', $patient->breed);
        $this->assertNotSame('other', $patient->species);

        $this->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Tenreque')
            ->assertSee('Leucístico');

        $this->post(route('patients.store'), [
            'tutor_id' => $tutor->id,
            'name' => 'Estrela',
            'species_choice' => (string) $species->id,
            'breed_choice' => (string) $breed->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, AnimalSpecies::query()->where('clinic_id', $clinic->id)->where('normalized_name', 'tenreque')->count());
        $this->assertSame(1, AnimalBreed::query()->where('clinic_id', $clinic->id)->where('normalized_name', 'leucistico')->count());
    }

    public function test_custom_catalog_is_clinic_scoped_and_cannot_be_used_by_another_clinic(): void
    {
        $clinicA = $this->clinic('Clínica Catálogo A', '00000000000403');
        $clinicB = $this->clinic('Clínica Catálogo B', '00000000000404');
        $tutorB = $this->tutor($clinicB, 'Responsável B');
        $userA = $this->userForClinic($clinicA);
        $userB = $this->userForClinic($clinicB);

        $this->actingAs($userA)->post(route('patient-catalog.species.store'), [
            'name' => 'Espécie exclusiva A',
            'category' => 'Silvestres e outros',
        ])->assertSessionDoesntHaveErrors();

        $speciesA = AnimalSpecies::query()->where('clinic_id', $clinicA->id)->firstOrFail();

        $this->post(route('patient-catalog.breeds.store'), [
            'animal_species_id' => $speciesA->id,
            'name' => 'Variedade exclusiva A',
        ])->assertSessionDoesntHaveErrors();

        $breedA = AnimalBreed::query()->where('clinic_id', $clinicA->id)->firstOrFail();

        $this->actingAs($userB)
            ->get(route('patient-catalog.species'))
            ->assertOk()
            ->assertDontSee('Espécie exclusiva A');

        $this->post(route('patients.store'), [
            'tutor_id' => $tutorB->id,
            'name' => 'Paciente bloqueado',
            'species_choice' => (string) $speciesA->id,
            'breed_choice' => (string) $breedA->id,
        ])->assertSessionHasErrors('species_choice');

        $this->assertDatabaseMissing('patients', ['name' => 'Paciente bloqueado']);
    }

    public function test_expandable_navigation_shows_catalog_and_keeps_current_group_open(): void
    {
        $clinic = $this->clinic('Clínica Menu', '00000000000405');
        $user = $this->userForClinic($clinic);

        $response = $this->actingAs($user)
            ->get(route('patient-catalog.species'))
            ->assertOk()
            ->assertSee('Atendimento clínico')
            ->assertSee('Cadastros')
            ->assertSee('Raças e variedades');

        $this->assertMatchesRegularExpression(
            '/<details class="nav-group"\s+open\s*>/',
            $response->getContent()
        );
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

    private function tutor(Clinic $clinic, string $name): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'phone' => '21999990000',
            'active' => true,
        ]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create(['active' => true, 'clinic_id' => $clinic->id]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'patients.manage'],
            ['name' => 'Gerenciar pacientes', 'description' => 'Teste', 'group' => 'Atendimento', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Catálogo '.Str::random(6),
            'slug' => 'catalogo-'.Str::lower(Str::random(8)),
            'description' => 'Teste do catálogo',
            'system' => false,
            'active' => true,
        ]);
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
