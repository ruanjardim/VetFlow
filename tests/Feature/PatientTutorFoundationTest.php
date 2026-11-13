<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientTutorFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_can_create_patient_only_for_tutor_from_own_clinic(): void
    {
        $clinic = $this->clinic('Clínica Própria', '12345678000190');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000191');
        $ownTutor = $this->tutor($clinic, 'Tutor Próprio', '52998224725');
        $otherTutor = $this->tutor($otherClinic, 'Tutor Externo', '11144477735');
        $user = $this->authorizedUser($clinic);

        $this->actingAs($user)
            ->post(route('patients.store'), [
                'tutor_id' => $otherTutor->id,
                'name' => 'Paciente indevido',
            ])
            ->assertSessionHasErrors('tutor_id');

        $this->post(route('patients.store'), [
            'tutor_id' => $ownTutor->id,
            'name' => 'Paciente inválido',
            'birth_date' => '2999-12-31',
            'weight' => 0,
            'microchip' => Str::repeat('C', 101),
            'has_pedigree' => 'talvez',
            'pedigree_number' => Str::repeat('P', 121),
            'markings' => Str::repeat('M', 1001),
        ])->assertSessionHasErrors([
            'birth_date',
            'weight',
            'microchip',
            'has_pedigree',
            'pedigree_number',
            'markings',
        ]);

        $this->post(route('patients.store'), [
            'tutor_id' => $ownTutor->id,
            'name' => 'Luna',
            'species' => 'Canino',
            'microchip' => '  CHIP-001  ',
            'has_pedigree' => '1',
            'pedigree_number' => '  CBKC/RG-7788  ',
            'markings' => '  Mancha branca na pata direita  ',
        ])->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'tutor_id' => $ownTutor->id,
            'name' => 'Luna',
            'microchip' => 'CHIP-001',
            'has_pedigree' => true,
            'pedigree_number' => 'CBKC/RG-7788',
            'markings' => 'Mancha branca na pata direita',
        ]);
        $patient = Patient::query()->where('name', 'Luna')->sole();
        $this->assertNull($patient->neutered);
        $this->assertTrue($patient->has_pedigree);

        $this->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Possui pedigree')
            ->assertSee('CBKC/RG-7788');
        $this->assertDatabaseMissing('patients', [
            'name' => 'Paciente indevido',
        ]);
        $this->assertDatabaseMissing('patients', [
            'name' => 'Paciente inválido',
        ]);
    }

    public function test_global_user_patient_inherits_selected_tutor_clinic(): void
    {
        $clinic = $this->clinic('Clínica Central', '12345678000192');
        $tutor = $this->tutor($clinic, 'Tutor Central', '52998224725');
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->post(route('patients.store'), [
                'tutor_id' => $tutor->id,
                'name' => 'Thor',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Thor',
        ]);
    }

    public function test_clinic_user_can_update_patient_with_any_species_for_an_own_tutor(): void
    {
        $clinic = $this->clinic('Clínica Própria', '12345678000193');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000194');
        $originalTutor = $this->tutor($clinic, 'Tutor Original', '52998224725');
        $replacementTutor = $this->tutor($clinic, 'Tutor Atualizado', '11144477735');
        $otherTutor = $this->tutor($otherClinic, 'Tutor Externo', '93541134780');
        $user = $this->authorizedUser($clinic);
        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $originalTutor->id,
            'name' => 'Luna',
            'species' => 'Canino',
        ]);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                'tutor_id' => $replacementTutor->id,
                'name' => 'Estrela',
                'species' => 'Equino',
                'breed' => 'Mangalarga',
                'gender' => 'Fêmea',
                'birth_date' => '2020-01-15',
                'weight' => 450.50,
                'microchip' => 'EQ-2048',
                'has_pedigree' => '0',
                'markings' => 'Lista estreita na face.',
                'notes' => 'Paciente de grande porte.',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
            'tutor_id' => $replacementTutor->id,
            'name' => 'Estrela',
            'species' => 'Equino',
            'breed' => 'Mangalarga',
            'microchip' => 'EQ-2048',
            'has_pedigree' => false,
            'markings' => 'Lista estreita na face.',
        ]);

        $this->put(route('patients.update', $patient), [
            'tutor_id' => $otherTutor->id,
            'name' => 'Estrela',
        ])->assertSessionHasErrors('tutor_id');
    }

    public function test_patient_search_finds_identification_fields_without_crossing_clinics(): void
    {
        $clinic = $this->clinic('Clínica da Busca', '12345678000200');
        $otherClinic = $this->clinic('Clínica Isolada', '12345678000201');
        $tutor = $this->tutor($clinic, 'Tutora Renata', '52998224725');
        $otherTutor = $this->tutor($otherClinic, 'Tutor Externo', '11144477735');
        $user = $this->authorizedUser($clinic);

        Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Luna Identificada',
            'microchip' => 'CHIP-BUSCA-001',
            'pedigree_number' => 'CBKC/BUSCA-2048',
            'markings' => 'Ponta da orelha direita branca',
        ]);
        Patient::query()->create([
            'clinic_id' => $otherClinic->id,
            'tutor_id' => $otherTutor->id,
            'name' => 'Paciente de Outra Clínica',
            'microchip' => 'CHIP-EXTERNO-999',
            'pedigree_number' => 'REGISTRO-EXTERNO-999',
            'markings' => 'Marca secreta externa',
        ]);

        $this->actingAs($user)
            ->get(route('patients.index', ['q' => 'CHIP-BUSCA']))
            ->assertOk()
            ->assertSee('Luna Identificada')
            ->assertSee('CHIP-BUSCA-001');

        $this->get(route('patients.index', ['q' => 'orelha direita']))
            ->assertOk()
            ->assertSee('Luna Identificada');

        $this->get(route('patients.index', ['q' => 'CBKC/BUSCA']))
            ->assertOk()
            ->assertSee('Luna Identificada');

        $this->get(route('patients.index', ['q' => 'CHIP-EXTERNO']))
            ->assertOk()
            ->assertDontSee('Paciente de Outra Clínica')
            ->assertSee('Nenhum paciente encontrado para esta busca.');

        $this->get(route('patients.index', ['q' => 'REGISTRO-EXTERNO']))
            ->assertOk()
            ->assertDontSee('Paciente de Outra Clínica')
            ->assertSee('Nenhum paciente encontrado para esta busca.');
    }

    public function test_patient_photo_and_neutered_status_are_stored_privately(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica da Foto', '12345678000195');
        $tutor = $this->tutor($clinic, 'Tutor da Foto', '52998224725');
        $user = $this->authorizedUser($clinic);

        $this->actingAs($user)
            ->post(route('patients.store'), [
                'tutor_id' => $tutor->id,
                'name' => 'Luna Foto',
                'neutered' => '1',
                'photo' => $this->fakePng('luna.png', 400),
            ])
            ->assertRedirect(route('patients.index'));

        $patient = Patient::query()->where('name', 'Luna Foto')->sole();

        $this->assertTrue($patient->neutered);
        $this->assertNotNull($patient->photo_path);
        $this->assertStringStartsWith('patients/'.$clinic->id.'/photos/', $patient->photo_path);
        Storage::disk('local')->assertExists($patient->photo_path);

        $this->get(route('patients.photo', $patient))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=300, private')
            ->assertHeader('x-content-type-options', 'nosniff');

        $this->get(route('patients.edit', $patient))
            ->assertOk()
            ->assertSee('Esterilização')
            ->assertSee(route('patients.photo', $patient), false);

        $this->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Castrado / esterilizado')
            ->assertSee(route('patients.photo', $patient), false);

        $this->get(route('patients.index'))
            ->assertOk()
            ->assertSee('Luna Foto')
            ->assertSee('Esterilização');
    }

    public function test_patient_photo_can_be_replaced_and_removed_without_orphaning_the_previous_file(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica da Troca', '12345678000196');
        $tutor = $this->tutor($clinic, 'Tutor da Troca', '11144477735');
        $user = $this->authorizedUser($clinic);
        $oldPath = 'patients/'.$clinic->id.'/photos/old.jpg';
        Storage::disk('local')->put($oldPath, 'old-photo');
        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Thor Foto',
            'photo_path' => $oldPath,
        ]);

        $this->actingAs($user)
            ->put(route('patients.update', $patient), [
                'tutor_id' => $tutor->id,
                'name' => 'Thor Foto',
                'neutered' => '0',
                'photo' => $this->fakePng('thor.png', 450),
                'remove_photo' => '1',
            ])
            ->assertRedirect(route('patients.index'));

        $patient->refresh();
        $newPath = $patient->photo_path;

        $this->assertFalse($patient->neutered);
        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        $this->put(route('patients.update', $patient), [
            'tutor_id' => $tutor->id,
            'name' => 'Thor Foto',
            'neutered' => '0',
            'remove_photo' => '1',
        ])->assertRedirect(route('patients.index'));

        $this->assertNull($patient->refresh()->photo_path);
        Storage::disk('local')->assertMissing($newPath);
    }

    public function test_patient_photo_rejects_unsupported_or_oversized_files(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica da Validação', '12345678000197');
        $tutor = $this->tutor($clinic, 'Tutor da Validação', '93541134780');
        $user = $this->authorizedUser($clinic);

        $this->actingAs($user)
            ->post(route('patients.store'), [
                'tutor_id' => $tutor->id,
                'name' => 'Foto SVG',
                'photo' => UploadedFile::fake()->create('patient.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('photo');

        $this->post(route('patients.store'), [
            'tutor_id' => $tutor->id,
            'name' => 'Foto Grande',
            'photo' => $this->fakePng('large.png', 5121),
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('patients', ['name' => 'Foto SVG']);
        $this->assertDatabaseMissing('patients', ['name' => 'Foto Grande']);
    }

    public function test_patient_photo_route_respects_clinic_isolation(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Autorizada', '12345678000198');
        $otherClinic = $this->clinic('Clínica Sem Acesso', '12345678000199');
        $tutor = $this->tutor($clinic, 'Tutor Protegido', '52998224725');
        $ownUser = $this->authorizedUser($clinic);
        $otherUser = $this->authorizedUser($otherClinic);
        $path = 'patients/'.$clinic->id.'/photos/protected.jpg';
        Storage::disk('local')->put($path, 'protected-photo');
        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Paciente Protegido',
            'photo_path' => $path,
        ]);

        $this->actingAs($ownUser)
            ->get(route('patients.photo', $patient))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route('patients.photo', $patient))
            ->assertNotFound();
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

    private function fakePng(string $name, int $kilobytes = 1): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        $targetBytes = max(strlen($png), $kilobytes * 1024);

        return UploadedFile::fake()->createWithContent(
            $name,
            str_pad($png, $targetBytes, "\0")
        );
    }

    private function tutor(Clinic $clinic, string $name, string $cpf): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cpf' => $cpf,
            'phone' => '21999990001',
            'active' => true,
        ]);
    }

    private function authorizedUser(?Clinic $clinic = null): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'patients.manage'], [
            'name' => 'Gerenciar pacientes',
            'description' => 'Gerenciar pacientes',
            'group' => 'Tests',
            'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Pacientes '.Str::random(6),
            'slug' => 'patients-'.Str::lower(Str::random(8)),
            'description' => 'Test role',
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
