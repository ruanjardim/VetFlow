<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationImport;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImplementationImportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_import_creates_a_durable_summary_without_row_data(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Central', '12345678000190');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'nome,telefone,whatsapp,email,cpf_cnpj,endereco,observacoes',
            'Maria da Silva,21999990001,,,52998224725,,',
        ]);

        $this->post(route('implementation.tutors.upload'), [
            'tutors_file' => UploadedFile::fake()
                ->createWithContent('implantacao-tutores.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->post(route('implementation.tutors.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $history = ImplementationImport::query()->sole();

        $this->assertSame($clinic->id, $history->clinic_id);
        $this->assertSame($user->id, $history->user_id);
        $this->assertSame('Clínica Central', $history->clinic_name);
        $this->assertSame($user->name, $history->user_name);
        $this->assertSame('tutors', $history->entity_type);
        $this->assertSame('Responsáveis', $history->entity_label);
        $this->assertSame('csv', $history->data_source);
        $this->assertSame('implantacao-tutores.csv', $history->file_name);
        $this->assertSame(1, $history->total_rows);
        $this->assertSame(1, $history->imported_count);
        $this->assertSame(0, $history->invalid_rows);
        $this->assertArrayNotHasKey('rows', $history->getAttributes());

        $this->delete(route('implementation.reset'))
            ->assertRedirect(route('implementation.index'));

        $this->get(route('implementation.index'))
            ->assertOk()
            ->assertSee('Importações recentes')
            ->assertSee('implantacao-tutores.csv')
            ->assertSee('Clínica Central')
            ->assertSee($user->name);
    }

    public function test_clinic_user_only_sees_history_from_their_own_clinic(): void
    {
        $ownClinic = $this->clinic('Clínica Própria', '12345678000191');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000192');
        $user = $this->authorizedUser($ownClinic);

        $this->history($ownClinic, $user, 'propria.csv');
        $this->history($otherClinic, $user, 'externa.csv');

        $this->actingAs($user)
            ->get(route('implementation.index'))
            ->assertOk()
            ->assertSee('propria.csv')
            ->assertDontSee('externa.csv')
            ->assertDontSee('Clínica Externa');
    }

    public function test_onboarding_readiness_uses_the_latest_successful_block_per_accessible_clinic(): void
    {
        $ownClinic = $this->clinic('Clínica Horizonte', '12345678000194');
        $otherClinic = $this->clinic('Clínica Oculta', '12345678000195');
        $user = $this->authorizedUser($ownClinic);

        $this->history(
            $ownClinic,
            $user,
            'tutores-antigo.csv',
            'tutors',
            now()->subDay(),
            1
        );
        $this->history($ownClinic, $user, 'tutores-atual.csv', 'tutors', now(), 2);
        $this->history($ownClinic, $user, 'pacientes.csv', 'patients', now(), 3);
        $this->history($otherClinic, $user, 'produtos.csv', 'products', now(), 4);

        $response = $this->actingAs($user)->get(route('implementation.index'));

        $response
            ->assertOk()
            ->assertSee('Cobertura da implantação')
            ->assertSee('2 de 6 blocos concluídos')
            ->assertSee('33%')
            ->assertSee('2 registros via CSV')
            ->assertDontSee('Clínica Oculta');

        $readiness = $response->viewData('onboardingReadiness');

        $this->assertCount(1, $readiness);
        $this->assertSame($ownClinic->id, $readiness[0]['clinic_id']);
        $this->assertSame(2, $readiness[0]['completed_blocks']);
        $this->assertSame(6, $readiness[0]['total_blocks']);
        $this->assertSame(33, $readiness[0]['percentage']);
        $this->assertSame(
            2,
            collect($readiness[0]['blocks'])->firstWhere('type', 'tutors')['imported_count']
        );
        $this->assertFalse(
            collect($readiness[0]['blocks'])->firstWhere('type', 'financial')['completed']
        );
    }

    public function test_invalid_import_does_not_create_history(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Norte', '12345678000193');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'nome,telefone,whatsapp,email,cpf_cnpj,endereco,observacoes',
            ',,,,,,',
        ]);

        $this->post(route('implementation.tutors.upload'), [
            'tutors_file' => UploadedFile::fake()
                ->createWithContent('invalido.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->post(route('implementation.tutors.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('tutors', 0);
        $this->assertDatabaseCount('implementation_imports', 0);
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

    private function history(
        Clinic $clinic,
        User $user,
        string $fileName,
        string $entityType = 'tutors',
        ?DateTimeInterface $completedAt = null,
        int $importedCount = 1
    ): ImplementationImport {
        $labels = [
            'tutors' => 'Responsáveis',
            'patients' => 'Pacientes',
            'suppliers' => 'Fornecedores',
            'products' => 'Produtos',
            'stock' => 'Estoque inicial',
            'financial' => 'Financeiro',
        ];

        return ImplementationImport::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $user->name,
            'entity_type' => $entityType,
            'entity_label' => $labels[$entityType],
            'data_source' => 'csv',
            'file_name' => $fileName,
            'total_rows' => $importedCount,
            'imported_count' => $importedCount,
            'invalid_rows' => 0,
            'completed_at' => $completedAt ?? now(),
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
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'implementation.manage'],
            [
                'name' => 'Gerenciar implantação',
                'description' => 'Gerenciar implantação',
                'group' => 'Tests',
                'active' => true,
            ]
        );
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
