<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImplementationTutorCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_import_tutors_from_csv_into_selected_clinic(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Central', '12345678000190');
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->post(route('implementation.clinic'), [
                'clinic_id' => $clinic->id,
            ])
            ->assertRedirect(route('implementation.index', ['step' => 2]));

        $this->post(route('implementation.source'), [
            'data_source' => 'csv',
        ])->assertRedirect(route('implementation.index', ['step' => 3]));

        $csv = implode("\n", [
            'nome;telefone;whatsapp;email;cpf_cnpj;endereco;observacoes',
            'Maria da Silva;21999990001;21988880001;maria@example.com;529.982.247-25;Rua das Flores 10;Cliente antiga',
            'João Souza;21999990002;;; ;Avenida Central 20;',
        ]);

        $this->post(route('implementation.tutors.upload'), [
            'tutors_file' => UploadedFile::fake()
                ->createWithContent('tutores.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 4]))
            ->assertOk()
            ->assertSee('Mapeamento automático')
            ->assertSee('Telefone principal')
            ->assertSee('Detectada');

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Todos os registros estão prontos')
            ->assertSee('2');

        $this->get(route('implementation.index', ['step' => 6]))
            ->assertOk()
            ->assertSee('Maria da Silva')
            ->assertSee('João Souza');

        $this->get(route('implementation.index', ['step' => 7]))
            ->assertOk()
            ->assertSee('Confirmar importação')
            ->assertSee('Clínica Central');

        $this->post(route('implementation.tutors.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $this->assertDatabaseHas('tutors', [
            'clinic_id' => $clinic->id,
            'name' => 'Maria da Silva',
            'phone' => '21999990001',
            'phone_secondary' => '21988880001',
            'email' => 'maria@example.com',
            'cpf' => '52998224725',
            'street' => 'Rua das Flores 10',
            'notes' => 'Cliente antiga',
            'active' => true,
        ]);
        $this->assertDatabaseHas('tutors', [
            'clinic_id' => $clinic->id,
            'name' => 'João Souza',
            'phone' => '21999990002',
            'cpf' => null,
        ]);

        $this->get(route('implementation.index', ['step' => 8]))
            ->assertOk()
            ->assertSee('Importação concluída')
            ->assertSee('Clínica Central')
            ->assertSee('2');

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('implementation/tutor-csv/'.$user->id)
        );
    }

    public function test_invalid_csv_is_not_imported_and_displays_row_errors(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Norte', '12345678000191');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'nome,telefone,whatsapp,email,cpf_cnpj,endereco,observacoes',
            ',,,email-invalido,11111111111,Rua sem nome,',
        ]);

        $this->post(route('implementation.tutors.upload'), [
            'tutors_file' => UploadedFile::fake()
                ->createWithContent('tutores.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Informe o nome do tutor.')
            ->assertSee('Informe o telefone principal do tutor.')
            ->assertSee('Informe um e-mail válido.')
            ->assertSee('O CPF informado é inválido.')
            ->assertSee('Substituir CSV');

        $this->post(route('implementation.tutors.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('tutors', 0);
    }

    public function test_clinic_user_cannot_select_another_clinic_for_import(): void
    {
        $ownClinic = $this->clinic('Clínica Própria', '12345678000192');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000193');
        $user = $this->authorizedUser($ownClinic);

        $this->actingAs($user)
            ->get(route('implementation.index'))
            ->assertOk()
            ->assertSee('Clínica Própria')
            ->assertDontSee('Clínica Externa');

        $this->post(route('implementation.clinic'), [
            'clinic_id' => $otherClinic->id,
        ])
            ->assertSessionHasErrors('clinic_id')
            ->assertSessionMissing('implementation.tutor_csv');

        $this->post(route('implementation.clinic'), [
            'clinic_id' => $ownClinic->id,
        ])->assertRedirect(route('implementation.index', ['step' => 2]));
    }

    public function test_user_cannot_skip_required_wizard_steps(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->get(route('implementation.index', ['step' => 7]))
            ->assertRedirect(route('implementation.index', ['step' => 1]))
            ->assertSessionHas('warning');
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
