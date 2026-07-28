<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImplementationPatientCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_import_patients_linked_to_tutors_from_csv(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Central', '12345678000190');
        $tutor = $this->tutor($clinic, 'Maria da Silva', '52998224725');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'tutor_documento;nome_pet;especie;raca;sexo;nascimento;peso;observacoes',
            '529.982.247-25;Luna;Canino;Spitz Alemão;Fêmea;12/05/2022;4,80;Paciente antiga',
            '529.982.247-25;Thor;Felino;SRD;Macho;2021-08-20;5.25;',
        ]);

        $this->post(route('implementation.patients.upload'), [
            'patients_file' => UploadedFile::fake()
                ->createWithContent('pacientes.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 4]))
            ->assertOk()
            ->assertSee('CPF do tutor responsável')
            ->assertSee('Nome do paciente')
            ->assertSee('Detectada');

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Todos os registros estão prontos')
            ->assertSee('2');

        $this->get(route('implementation.index', ['step' => 6]))
            ->assertOk()
            ->assertSee('Pré-visualização de Pacientes')
            ->assertSee('Maria da Silva')
            ->assertSee('Luna')
            ->assertSee('Thor');

        $this->get(route('implementation.index', ['step' => 7]))
            ->assertOk()
            ->assertSee('Importar Pacientes')
            ->assertSee('Clínica Central');

        $this->post(route('implementation.patients.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Luna',
            'species' => 'Canino',
            'breed' => 'Spitz Alemão',
            'gender' => 'Fêmea',
            'birth_date' => '2022-05-12 00:00:00',
            'weight' => 4.80,
            'notes' => 'Paciente antiga',
        ]);
        $this->assertDatabaseHas('patients', [
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Thor',
            'birth_date' => '2021-08-20 00:00:00',
            'weight' => 5.25,
        ]);

        $this->get(route('implementation.index', ['step' => 8]))
            ->assertOk()
            ->assertSee('Importação concluída')
            ->assertSee('Pacientes')
            ->assertSee('2');

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('implementation/patients-csv/'.$user->id)
        );
    }

    public function test_patient_csv_blocks_unknown_tutor_and_invalid_values(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Norte', '12345678000191');
        $this->tutor($clinic, 'Tutor Local', '11144477735');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'tutor_documento,nome_pet,especie,raca,sexo,nascimento,peso,observacoes',
            '52998224725,,Canino,,,31/02/2024,peso-invalido,',
            '11144477735,Futuro,Canino,,,31/12/2999,0,',
        ]);

        $this->post(route('implementation.patients.upload'), [
            'patients_file' => UploadedFile::fake()
                ->createWithContent('pacientes.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Informe o nome do paciente.')
            ->assertSee('Informe o nascimento em DD/MM/AAAA ou AAAA-MM-DD.')
            ->assertSee('Informe um peso válido.')
            ->assertSee('Nenhum tutor com este CPF foi encontrado')
            ->assertSee('A data de nascimento não pode estar no futuro.')
            ->assertSee('O peso deve ser maior que zero.')
            ->assertSee('Substituir CSV');

        $this->post(route('implementation.patients.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_patient_csv_cannot_link_tutor_from_another_clinic(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Própria', '12345678000192');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000193');
        $this->tutor($otherClinic, 'Tutor Externo', '52998224725');
        $user = $this->authorizedUser($clinic);
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'tutor_documento,nome_pet,especie,raca,sexo,nascimento,peso,observacoes',
            '52998224725,Luna,Canino,SRD,Fêmea,2022-05-12,8.5,',
        ]);

        $this->post(route('implementation.patients.upload'), [
            'patients_file' => UploadedFile::fake()
                ->createWithContent('pacientes.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Nenhum tutor com este CPF foi encontrado');

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_patient_template_is_available(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->get(route('implementation.templates', 'patients'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee(
                'tutor_documento,nome_pet,especie,raca,sexo,nascimento,peso,observacoes',
                false
            );
    }

    private function configureCsvImport(User $user, Clinic $clinic): void
    {
        $this->actingAs($user)->post(route('implementation.clinic'), [
            'clinic_id' => $clinic->id,
        ]);

        $this->post(route('implementation.source'), [
            'data_source' => 'csv',
        ]);
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
        $permission = Permission::query()->create([
            'name' => 'Gerenciar implantação',
            'slug' => 'implementation.manage',
            'description' => 'Gerenciar implantação',
            'group' => 'Tests',
            'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Implantação '.Str::random(6),
            'slug' => 'implementation-'.Str::lower(Str::random(8)),
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
