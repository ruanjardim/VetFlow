<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationImport;
use App\Modules\Implementation\Models\ImplementationPilotCheck;
use App\Modules\Implementation\Models\ImplementationPilotDecision;
use App\Modules\Implementation\Models\ImplementationPilotRelease;
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
            ->assertSee('Ver registros')
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

    public function test_quality_issue_queue_lists_only_accessible_clinic_records(): void
    {
        $ownClinic = $this->clinic('Clínica da Fila', '12345678000210');
        $otherClinic = $this->clinic('Clínica Oculta da Fila', '12345678000211');
        $user = $this->authorizedUser($ownClinic);

        DB::table('tutors')->insert([
            [
                'clinic_id' => $ownClinic->id,
                'name' => 'Responsável que precisa de revisão',
                'phone' => '21999990003',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clinic_id' => $otherClinic->id,
                'name' => 'Responsável que deve ficar oculto',
                'phone' => '21999990004',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('implementation.quality.issues', [
            'clinic' => $ownClinic->id,
            'type' => 'tutors',
        ]));

        $response
            ->assertOk()
            ->assertSee('Fila de revisão')
            ->assertSee('Responsável que precisa de revisão')
            ->assertSee('CPF não informado')
            ->assertSee('E-mail não informado')
            ->assertDontSee('Responsável que deve ficar oculto')
            ->assertSee('Solicite a correção ao responsável do módulo.');

        $this->get(route('implementation.quality.issues', [
            'clinic' => $otherClinic->id,
            'type' => 'tutors',
        ]))->assertNotFound();
    }

    public function test_pilot_history_consolidates_only_accessible_clinic_events(): void
    {
        $ownClinic = $this->clinic('Clínica Histórico', '12345678000212');
        $otherClinic = $this->clinic('Clínica Histórico Oculto', '12345678000213');
        $user = $this->authorizedUser($ownClinic);

        $this->history($ownClinic, $user, 'historico-proprio.csv');
        $this->history($otherClinic, $user, 'historico-oculto.csv');

        foreach ([$ownClinic, $otherClinic] as $clinic) {
            ImplementationPilotCheck::query()->create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'clinic_name' => $clinic->trade_name,
                'user_name' => $user->name,
                'check_key' => 'data_reviewed',
                'check_label' => $clinic->id === $ownClinic->id
                    ? 'Checklist próprio'
                    : 'Checklist oculto',
                'completed' => true,
                'decided_at' => now(),
            ]);
            ImplementationPilotRelease::query()->create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'clinic_name' => $clinic->trade_name,
                'user_name' => $user->name,
                'revision' => 1,
                'release_owner' => 'Operação '.$clinic->id,
                'support_owner' => 'Suporte '.$clinic->id,
                'scope' => $clinic->id === $ownClinic->id ? 'Escopo próprio' : 'Escopo oculto',
                'release_notes' => 'Notas da revisão.',
                'recorded_at' => now(),
            ]);
            ImplementationPilotDecision::query()->create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'clinic_name' => $clinic->trade_name,
                'user_name' => $user->name,
                'decision' => 'held',
                'evidence_snapshot' => [
                    'coverage' => ['completed' => 1],
                    'quality' => ['issues' => 2],
                    'checklist' => ['completed' => 1],
                ],
                'evidence_hash' => hash('sha256', 'clinic-'.$clinic->id),
                'notes' => $clinic->id === $ownClinic->id
                    ? 'Decisão própria em espera'
                    : 'Decisão oculta',
                'decided_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->get(route('implementation.pilots.history', $ownClinic));

        $response
            ->assertOk()
            ->assertSee('Histórico do piloto')
            ->assertSee('historico-proprio.csv')
            ->assertSee('Checklist próprio')
            ->assertSee('Escopo próprio')
            ->assertSee('Decisão própria em espera')
            ->assertDontSee('historico-oculto.csv')
            ->assertDontSee('Checklist oculto')
            ->assertDontSee('Escopo oculto')
            ->assertDontSee('Decisão oculta');

        $history = $response->viewData('history');
        $this->assertSame(1, $history['imports']->total());
        $this->assertSame(1, $history['checks']->total());
        $this->assertSame(1, $history['releases']->total());
        $this->assertSame(1, $history['decisions']->total());

        $this->get(route('implementation.pilots.history', $otherClinic))->assertNotFound();
    }

    public function test_pilot_report_is_printable_downloadable_and_tenant_safe(): void
    {
        $ownClinic = $this->clinic('Clínica Relatório', '12345678000214');
        $otherClinic = $this->clinic('Clínica Relatório Oculto', '12345678000215');
        $user = $this->authorizedUser($ownClinic);
        $this->history($ownClinic, $user, 'relatorio-responsaveis.csv');
        $this->history($otherClinic, $user, 'relatorio-oculto.csv');

        $html = $this->actingAs($user)->get(route('implementation.pilots.report', $ownClinic));

        $html
            ->assertOk()
            ->assertSee('Relatório do piloto')
            ->assertSee('Clínica Relatório')
            ->assertSee('1 registros via CSV')
            ->assertSee('Baixar JSON')
            ->assertDontSee('Clínica Relatório Oculto');

        $json = $this->get(route('implementation.pilots.report-json', $ownClinic));

        $json
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8');

        $payload = json_decode($json->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($ownClinic->id, $payload['clinic']['id']);
        $this->assertSame('Clínica Relatório', $payload['clinic']['name']);
        $this->assertSame(1, $payload['coverage']['completed_blocks']);
        $this->assertSame('blocked', $payload['readiness']['status']['key']);
        $this->assertSame(64, strlen($payload['readiness']['evidence_hash']));
        $this->assertStringContainsString(
            'attachment; filename=vetflow-piloto-clinica-relatorio-',
            (string) $json->headers->get('content-disposition')
        );

        $this->get(route('implementation.pilots.report', $otherClinic))->assertNotFound();
        $this->get(route('implementation.pilots.report-json', $otherClinic))->assertNotFound();
    }

    public function test_pilot_portfolio_summarizes_prioritizes_and_filters_clinics(): void
    {
        $blockedClinic = $this->clinic('Clínica Bloqueada', '12345678000216');
        $awaitingClinic = $this->clinic('Clínica Aguardando', '12345678000217');
        $user = $this->authorizedUser();

        foreach (['tutors', 'patients', 'suppliers', 'products', 'stock', 'financial'] as $type) {
            $this->history($awaitingClinic, $user, $type.'.csv', $type);
        }

        foreach (['data_reviewed', 'quality_resolved', 'access_validated', 'backup_aligned', 'training_completed'] as $key) {
            ImplementationPilotCheck::query()->create([
                'clinic_id' => $awaitingClinic->id,
                'user_id' => $user->id,
                'clinic_name' => $awaitingClinic->trade_name,
                'user_name' => $user->name,
                'check_key' => $key,
                'check_label' => $key,
                'completed' => true,
                'decided_at' => now(),
            ]);
        }

        ImplementationPilotRelease::query()->create([
            'clinic_id' => $awaitingClinic->id,
            'user_id' => $user->id,
            'clinic_name' => $awaitingClinic->trade_name,
            'user_name' => $user->name,
            'revision' => 1,
            'release_owner' => 'Operação',
            'support_owner' => 'Suporte',
            'scope' => 'Escopo validado.',
            'release_notes' => 'Aguardando decisão humana.',
            'recorded_at' => now(),
        ]);

        $all = $this->actingAs($user)->get(route('implementation.index'));
        $portfolio = $all->viewData('pilotPortfolio');

        $all
            ->assertOk()
            ->assertSee('Decisões superadas')
            ->assertSee('Exibindo 2 de 2 clínicas.');
        $this->assertSame(2, $portfolio['total']);
        $this->assertSame(1, $portfolio['counts']['blocked']);
        $this->assertSame(1, $portfolio['counts']['awaiting']);
        $this->assertSame(0, $portfolio['counts']['approved']);
        $this->assertSame($blockedClinic->id, $portfolio['items'][0]['clinic_id']);

        $filtered = $this->get(route('implementation.index', ['pilot_status' => 'awaiting']));
        $filteredPortfolio = $filtered->viewData('pilotPortfolio');
        $filteredReadiness = $filtered->viewData('pilotReadiness');

        $filtered->assertOk()->assertSee('Exibindo 1 de 2 clínicas.');
        $this->assertSame('awaiting', $filteredPortfolio['selected_status']);
        $this->assertSame(1, $filteredPortfolio['visible']);
        $this->assertCount(1, $filteredReadiness);
        $this->assertSame($awaitingClinic->id, $filteredReadiness[0]['clinic_id']);
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

    public function test_pilot_release_plan_keeps_revisions_and_respects_clinic_scope(): void
    {
        $ownClinic = $this->clinic('Clínica Liberação', '12345678000200');
        $otherClinic = $this->clinic('Clínica Plano Oculto', '12345678000201');
        $user = $this->authorizedUser($ownClinic);

        $firstRevision = [
            'clinic_id' => $ownClinic->id,
            'release_owner' => 'Ana Operações',
            'support_owner' => 'Bruno Suporte',
            'planned_start_date' => '2026-09-01',
            'scope' => 'Agenda e atendimento clínico.',
            'release_notes' => 'Primeira versão do plano.',
        ];

        $this->actingAs($user)
            ->post(route('implementation.pilot-releases.store'), $firstRevision)
            ->assertRedirect(route('implementation.index'));

        $this->post(route('implementation.pilot-releases.store'), [
            ...$firstRevision,
            'support_owner' => 'Carla Suporte',
            'scope' => 'Agenda, atendimento clínico e prescrições.',
            'release_notes' => 'Escopo ampliado após revisão.',
        ])->assertRedirect(route('implementation.index'));

        $this->from(route('implementation.index'))
            ->post(route('implementation.pilot-releases.store'), [
                ...$firstRevision,
                'clinic_id' => $otherClinic->id,
            ])
            ->assertRedirect(route('implementation.index'))
            ->assertSessionHasErrors('clinic_id');

        $releases = ImplementationPilotRelease::query()->orderBy('revision')->get();

        $this->assertCount(2, $releases);
        $this->assertSame(1, $releases[0]->revision);
        $this->assertSame(2, $releases[1]->revision);
        $this->assertSame('Bruno Suporte', $releases[0]->support_owner);
        $this->assertSame('Carla Suporte', $releases[1]->support_owner);
        $this->assertSame($user->id, $releases[1]->user_id);

        $response = $this->get(route('implementation.index'));
        $response
            ->assertOk()
            ->assertSee('Responsáveis, escopo e notas')
            ->assertSee('Revisão 2')
            ->assertSee('Carla Suporte')
            ->assertSee('Escopo ampliado após revisão.')
            ->assertDontSee('Clínica Plano Oculto');

        $pilotReleases = $response->viewData('pilotReleases');

        $this->assertCount(1, $pilotReleases);
        $this->assertSame($ownClinic->id, $pilotReleases[0]['clinic_id']);
        $this->assertTrue($pilotReleases[0]['has_release']);
        $this->assertSame(2, $pilotReleases[0]['revision']);
        $this->assertSame('Carla Suporte', $pilotReleases[0]['support_owner']);
        $this->assertSame($user->name, $pilotReleases[0]['user_name']);
    }

    public function test_pilot_decision_requires_current_evidence_and_becomes_stale_after_change(): void
    {
        $clinic = $this->clinic('Clínica Decisão', '12345678000202');
        $user = $this->authorizedUser($clinic);

        $this->actingAs($user)
            ->post(route('implementation.pilot-decisions.store'), [
                'clinic_id' => $clinic->id,
                'decision' => 'approved',
            ])
            ->assertRedirect(route('implementation.index'))
            ->assertSessionHas(
                'error',
                'A aprovação exige que os quatro portões de prontidão estejam atendidos.'
            );

        $this->assertDatabaseCount('implementation_pilot_decisions', 0);

        foreach (['tutors', 'patients', 'suppliers', 'products', 'stock', 'financial'] as $type) {
            $this->history($clinic, $user, $type.'.csv', $type);
        }

        $checkKeys = [
            'data_reviewed',
            'quality_resolved',
            'access_validated',
            'backup_aligned',
            'training_completed',
        ];

        foreach ($checkKeys as $key) {
            ImplementationPilotCheck::query()->create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'clinic_name' => $clinic->trade_name,
                'user_name' => $user->name,
                'check_key' => $key,
                'check_label' => $key,
                'completed' => true,
                'decided_at' => now(),
            ]);
        }

        ImplementationPilotRelease::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $user->name,
            'revision' => 1,
            'release_owner' => 'Responsável Piloto',
            'support_owner' => 'Responsável Suporte',
            'planned_start_date' => '2026-09-01',
            'scope' => 'Escopo piloto validado.',
            'release_notes' => 'Notas da primeira liberação.',
            'recorded_at' => now(),
        ]);

        $readyResponse = $this->get(route('implementation.index'));
        $ready = $readyResponse->viewData('pilotReadiness')[0];

        $readyResponse
            ->assertOk()
            ->assertSee('Prontidão para o piloto')
            ->assertSee('Aguardando decisão')
            ->assertSee('Aprovar piloto com as evidências atuais');
        $this->assertTrue($ready['gates_passed']);
        $this->assertSame('awaiting', $ready['status']['key']);
        $this->assertTrue(collect($ready['gates'])->every('passed'));

        $this->post(route('implementation.pilot-decisions.store'), [
            'clinic_id' => $clinic->id,
            'decision' => 'approved',
            'notes' => 'Aprovação para início controlado.',
        ])->assertRedirect(route('implementation.index'));

        $decision = ImplementationPilotDecision::query()->sole();

        $this->assertSame('approved', $decision->decision);
        $this->assertSame(64, strlen($decision->evidence_hash));
        $this->assertSame(6, $decision->evidence_snapshot['coverage']['completed']);
        $this->assertSame(1, $decision->evidence_snapshot['release']['revision']);

        $approved = $this->get(route('implementation.index'));
        $approvedReadiness = $approved->viewData('pilotReadiness')[0];
        $approved->assertOk()->assertSee('Piloto aprovado');
        $this->assertTrue($approvedReadiness['decision_current']);
        $this->assertSame('approved', $approvedReadiness['status']['key']);

        ImplementationPilotCheck::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $user->name,
            'check_key' => 'data_reviewed',
            'check_label' => 'Dados importados revisados',
            'completed' => false,
            'notes' => 'Nova amostra precisa ser conferida.',
            'decided_at' => now()->addSecond(),
        ]);

        $stale = $this->get(route('implementation.index'));
        $staleReadiness = $stale->viewData('pilotReadiness')[0];

        $stale
            ->assertOk()
            ->assertSee('Evidências pendentes')
            ->assertSee('decisão foi superada por mudanças nas evidências');
        $this->assertFalse($staleReadiness['decision_current']);
        $this->assertSame('blocked', $staleReadiness['status']['key']);
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
