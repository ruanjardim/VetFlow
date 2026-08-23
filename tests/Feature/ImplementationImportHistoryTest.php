<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationImport;
use App\Modules\Implementation\Models\ImplementationPilotCheck;
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

    public function test_onboarding_quality_only_evaluates_completed_blocks_in_the_accessible_clinic(): void
    {
        $ownClinic = $this->clinic('Clínica Qualidade', '12345678000196');
        $otherClinic = $this->clinic('Clínica Fora do Escopo', '12345678000197');
        $user = $this->authorizedUser($ownClinic);

        foreach (['tutors', 'suppliers', 'financial'] as $entityType) {
            $this->history(
                $ownClinic,
                $user,
                $entityType.'.csv',
                $entityType
            );
        }
        $this->history($otherClinic, $user, 'externo.csv', 'tutors');

        DB::table('tutors')->insert([
            [
                'clinic_id' => $ownClinic->id,
                'name' => 'Responsável sem dados recomendados',
                'phone' => '21999990001',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clinic_id' => $otherClinic->id,
                'name' => 'Responsável externo',
                'phone' => '21999990002',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('suppliers')->insert([
            'clinic_id' => $ownClinic->id,
            'name' => 'Fornecedor completo',
            'document' => '12345678000195',
            'email' => 'fornecedor@example.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('financial_transactions')->insert([
            'clinic_id' => $ownClinic->id,
            'type' => 'expense',
            'description' => 'Conta sem vencimento',
            'amount' => 100,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('implementation.index'));

        $response
            ->assertOk()
            ->assertSee('Pendências do onboarding')
            ->assertSee('2 pendências em 3 blocos avaliados')
            ->assertSee('1 de 3 blocos avaliados sem pendências detectadas')
            ->assertDontSee('Clínica Fora do Escopo');

        $quality = $response->viewData('onboardingQuality');
        $blocks = collect($quality[0]['blocks'])->keyBy('type');

        $this->assertCount(1, $quality);
        $this->assertSame($ownClinic->id, $quality[0]['clinic_id']);
        $this->assertSame(3, $quality[0]['evaluated_blocks']);
        $this->assertSame(1, $quality[0]['ready_blocks']);
        $this->assertSame(2, $quality[0]['total_issues']);
        $this->assertSame(33, $quality[0]['percentage']);
        $this->assertSame(1, $blocks['tutors']['issue_count']);
        $this->assertSame('ready', $blocks['suppliers']['status']);
        $this->assertSame(1, $blocks['financial']['issue_count']);
        $this->assertSame('awaiting', $blocks['patients']['status']);
        $this->assertNull($blocks['patients']['issue_count']);
    }

    public function test_pilot_checklist_preserves_decision_history_and_clinic_scope(): void
    {
        $ownClinic = $this->clinic('Clínica Piloto', '12345678000198');
        $otherClinic = $this->clinic('Clínica Não Autorizada', '12345678000199');
        $user = $this->authorizedUser($ownClinic);

        $this->actingAs($user)
            ->post(route('implementation.pilot-checks.store'), [
                'clinic_id' => $ownClinic->id,
                'check_key' => 'data_reviewed',
                'completed' => '1',
                'notes' => 'Amostra conferida com a equipe.',
            ])
            ->assertRedirect(route('implementation.index'));

        $this->post(route('implementation.pilot-checks.store'), [
            'clinic_id' => $ownClinic->id,
            'check_key' => 'data_reviewed',
            'completed' => '0',
            'notes' => 'Reaberto para uma nova conferência.',
        ])->assertRedirect(route('implementation.index'));

        $this->from(route('implementation.index'))
            ->post(route('implementation.pilot-checks.store'), [
                'clinic_id' => $otherClinic->id,
                'check_key' => 'data_reviewed',
                'completed' => '1',
            ])
            ->assertRedirect(route('implementation.index'))
            ->assertSessionHasErrors('clinic_id');

        $decisions = ImplementationPilotCheck::query()->orderBy('id')->get();

        $this->assertCount(2, $decisions);
        $this->assertTrue($decisions[0]->completed);
        $this->assertFalse($decisions[1]->completed);
        $this->assertSame($user->id, $decisions[1]->user_id);
        $this->assertSame('Reaberto para uma nova conferência.', $decisions[1]->notes);

        $response = $this->get(route('implementation.index'));
        $response
            ->assertOk()
            ->assertSee('Checklist auditável')
            ->assertSee('0 de 5 itens concluídos')
            ->assertSee('Reaberto para uma nova conferência.')
            ->assertSee($user->name)
            ->assertDontSee('Clínica Não Autorizada');

        $checklists = $response->viewData('pilotChecklists');
        $dataReviewed = collect($checklists[0]['checks'])->firstWhere(
            'key',
            'data_reviewed'
        );

        $this->assertCount(1, $checklists);
        $this->assertSame($ownClinic->id, $checklists[0]['clinic_id']);
        $this->assertSame(0, $checklists[0]['completed_checks']);
        $this->assertFalse($dataReviewed['completed']);
        $this->assertTrue($dataReviewed['has_decision']);
        $this->assertSame($user->name, $dataReviewed['user_name']);
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
