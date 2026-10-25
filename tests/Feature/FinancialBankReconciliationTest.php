<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialAccount;
use App\Modules\Financial\Models\FinancialReconciliation;
use App\Modules\Financial\Models\FinancialStatementEntry;
use App\Modules\Financial\Models\FinancialStatementImport;
use App\Modules\Financial\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialBankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_accounts_are_created_updated_and_kept_inside_the_selected_clinic(): void
    {
        [$clinic, $user] = $this->context('Clínica Bancária', '00000000001101');
        [$foreignClinic] = $this->context('Clínica Bancária Externa', '00000000001102');
        $foreign = $this->account($foreignClinic, 'Conta secreta externa');

        $this->actingAs($user)->post(route('financial-bank-reconciliation.accounts.store'), [
            'name' => 'Conta principal',
            'institution' => 'Banco Teste',
            'type' => 'checking',
            'account_number_last4' => '1234',
        ])->assertRedirect();

        $account = FinancialAccount::query()->withoutGlobalScope('clinic_tenant')->where('clinic_id', $clinic->id)->firstOrFail();
        $this->assertSame('Conta principal', $account->name);
        $this->assertDatabaseHas('audit_events', ['event' => 'financial.bank-account.created', 'clinic_id' => $clinic->id]);

        $this->put(route('financial-bank-reconciliation.accounts.update', $account->id), [
            'name' => 'Conta principal revisada',
            'institution' => 'Banco Teste',
            'type' => 'payment',
            'account_number_last4' => '4321',
        ])->assertRedirect();
        $this->assertDatabaseHas('financial_accounts', [
            'id' => $account->id,
            'name' => 'Conta principal revisada',
            'active' => false,
        ]);

        $this->get(route('financial-bank-reconciliation.index'))
            ->assertOk()
            ->assertSee('Conta principal revisada')
            ->assertDontSee($foreign->name);

        $this->post(route('financial-bank-reconciliation.accounts.store'), [
            'name' => 'Conta principal revisada',
            'type' => 'checking',
        ])->assertSessionHasErrors('name');
    }

    public function test_csv_and_ofx_imports_are_parsed_and_deduplicated_without_storing_the_source_file(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Extrato', '00000000001103');
        $account = $this->account($clinic, 'Conta extrato');
        $csv = "data;descrição;valor;id\n20/10/2026;Recebimento PIX;150,00;PIX-1\n21/10/2026;Pagamento fornecedor;-40,50;PAG-1\n";

        $this->actingAs($user)->post(route('financial-bank-reconciliation.imports.store'), [
            'account_id' => $account->id,
            'statement' => UploadedFile::fake()->createWithContent('extrato.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('financial_statement_imports', 1);
        $this->assertDatabaseCount('financial_statement_entries', 2);
        $this->assertDatabaseHas('financial_statement_entries', [
            'clinic_id' => $clinic->id,
            'description' => 'Pagamento fornecedor',
            'amount' => -40.50,
        ]);

        $this->post(route('financial-bank-reconciliation.imports.store'), [
            'account_id' => $account->id,
            'statement' => UploadedFile::fake()->createWithContent('extrato-repetido.csv', $csv),
        ])->assertSessionHasErrors('statement');

        $csvWithDuplicate = $csv."22/10/2026;Tarifa;-10,00;TAR-1\n";
        $this->post(route('financial-bank-reconciliation.imports.store'), [
            'account_id' => $account->id,
            'statement' => UploadedFile::fake()->createWithContent('extrato-complementar.csv', $csvWithDuplicate),
        ])->assertRedirect();
        $secondImport = FinancialStatementImport::query()->withoutGlobalScope('clinic_tenant')->latest('id')->firstOrFail();
        $this->assertSame(1, $secondImport->imported_rows);
        $this->assertSame(2, $secondImport->duplicate_rows);

        $ofx = '<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>'
            .'<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20261023<TRNAMT>75.25<FITID>OFX-1<MEMO>Crédito OFX</STMTTRN>'
            .'</BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>';
        $this->post(route('financial-bank-reconciliation.imports.store'), [
            'account_id' => $account->id,
            'statement' => UploadedFile::fake()->createWithContent('extrato.ofx', $ofx),
        ])->assertRedirect();

        $this->assertDatabaseHas('financial_statement_entries', ['description' => 'Crédito OFX', 'amount' => 75.25]);
        $this->assertDatabaseCount('financial_statement_entries', 4);
        $this->assertDatabaseHas('audit_events', ['event' => 'financial.bank-statement.imported', 'clinic_id' => $clinic->id]);
    }

    public function test_only_paid_same_type_and_amount_transactions_are_suggested_within_seven_days(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Sugestões', '00000000001104');
        $account = $this->account($clinic, 'Conta sugestões');
        $this->transaction($clinic, 'income', 'Receita compatível', 150, 'paid', '2026-10-20 09:00:00');
        $this->transaction($clinic, 'income', 'Receita longe demais', 150, 'paid', '2026-09-01 09:00:00');
        $this->transaction($clinic, 'income', 'Receita pendente', 150, 'pending', null);
        $this->transaction($clinic, 'expense', 'Despesa com mesmo valor', 150, 'paid', '2026-10-20 09:00:00');
        [$foreignClinic] = $this->context('Clínica Sugestões Externa', '00000000001105');
        $this->transaction($foreignClinic, 'income', 'Receita externa', 150, 'paid', '2026-10-20 09:00:00');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;PIX recebido;150,00;SUG-1\n");

        $this->get(route('financial-bank-reconciliation.index', [
            'account_id' => $account->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertSee('Receita compatível')
            ->assertDontSee('Receita longe demais')
            ->assertDontSee('Receita pendente')
            ->assertDontSee('Despesa com mesmo valor')
            ->assertDontSee('Receita externa');
    }

    public function test_confirmation_and_reversal_are_manual_audited_and_do_not_mutate_the_ledger(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Confirmação', '00000000001106');
        $account = $this->account($clinic, 'Conta confirmação');
        $transaction = $this->transaction($clinic, 'expense', 'Despesa paga', 80, 'paid', '2026-10-20 09:00:00');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;Débito bancário;-80,00;CONF-1\n");
        $entry = FinancialStatementEntry::query()->withoutGlobalScope('clinic_tenant')->firstOrFail();

        $this->post(route('financial-bank-reconciliation.reconciliations.store'), [
            'account_id' => $account->id,
            'entry_id' => $entry->id,
            'transaction_id' => $transaction->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ])->assertRedirect();

        $match = FinancialReconciliation::query()->withoutGlobalScope('clinic_tenant')->firstOrFail();
        $this->assertSame('confirmed', $match->status);
        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('Despesa paga', $transaction->fresh()->description);
        $this->assertDatabaseHas('audit_events', ['event' => 'financial.bank-reconciliation.confirmed', 'clinic_id' => $clinic->id]);

        $this->patch(route('financial-bank-reconciliation.reconciliations.reverse', $match->id), [
            'account_id' => $account->id,
            'reason' => 'Correspondência selecionada incorretamente.',
        ])->assertRedirect();
        $this->assertSame('reversed', $match->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['event' => 'financial.bank-reconciliation.reversed', 'clinic_id' => $clinic->id]);

        [$foreignClinic, $foreignUser] = $this->context('Clínica Confirmação Externa', '00000000001107');
        $this->actingAs($foreignUser)->post(route('financial-bank-reconciliation.reconciliations.store'), [
            'account_id' => $account->id,
            'entry_id' => $entry->id,
            'transaction_id' => $transaction->id,
        ])->assertNotFound();
    }

    public function test_export_is_private_formula_safe_filtered_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Exportação Bancária', '00000000001108');
        $account = $this->account($clinic, 'Conta exportação');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;=Fórmula perigosa;10,00;EXP-1\n");
        [$foreignClinic] = $this->context('Clínica Exportação Bancária Externa', '00000000001109');
        $foreign = $this->account($foreignClinic, 'Conta externa');
        $this->actingAs($this->userForClinic($foreignClinic))->post(route('financial-bank-reconciliation.imports.store'), [
            'account_id' => $foreign->id,
            'statement' => UploadedFile::fake()->createWithContent('externo.csv', "data;descricao;valor;id\n20/10/2026;Segredo externo;999,00;EXT-1\n"),
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('financial-bank-reconciliation.export', [
            'account_id' => $account->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'status' => 'pending',
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-robots-tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Fórmula perigosa", $csv);
        $this->assertStringNotContainsString('Segredo externo', $csv);
        $this->assertStringNotContainsString('999,00', $csv);
    }

    public function test_manual_match_outside_the_seven_day_window_requires_an_explanation(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Manual', '00000000001110');
        $account = $this->account($clinic, 'Conta manual');
        $transaction = $this->transaction($clinic, 'income', 'Receita antiga', 120, 'paid', '2026-09-30 09:00:00');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;Crédito bancário;120,00;MAN-1\n");
        $entry = FinancialStatementEntry::query()->withoutGlobalScope('clinic_tenant')->firstOrFail();

        $payload = [
            'account_id' => $account->id,
            'entry_id' => $entry->id,
            'transaction_id' => $transaction->id,
        ];

        $this->post(route('financial-bank-reconciliation.reconciliations.store'), $payload)
            ->assertSessionHasErrors('note');
        $this->assertDatabaseCount('financial_reconciliations', 0);

        $this->post(route('financial-bank-reconciliation.reconciliations.store'), $payload + [
            'note' => 'Recebimento bancário confirmado no fechamento mensal.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_reconciliations', [
            'financial_statement_entry_id' => $entry->id,
            'financial_transaction_id' => $transaction->id,
            'match_kind' => 'manual',
        ]);
    }

    public function test_ambiguous_suggestions_require_an_explicit_choice(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Ambiguidade', '00000000001111');
        $account = $this->account($clinic, 'Conta ambígua');
        $this->transaction($clinic, 'income', 'Receita candidata A', 150, 'paid', '2026-10-19 09:00:00');
        $this->transaction($clinic, 'income', 'Receita candidata B', 150, 'paid', '2026-10-20 09:00:00');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;PIX agrupado;150,00;AMB-1\n");

        $this->get(route('financial-bank-reconciliation.index', [
            'account_id' => $account->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertSee('2 correspondências possíveis')
            ->assertSee('Escolha após conferir')
            ->assertSee('Receita candidata A')
            ->assertSee('Receita candidata B');
    }

    public function test_dashboard_exposes_coverage_and_pending_age_without_changing_entries(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Cobertura', '00000000001112');
        $account = $this->account($clinic, 'Conta cobertura');
        $matched = $this->transaction($clinic, 'income', 'Receita conciliada', 100, 'paid', '2026-10-20 09:00:00');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;Recente;100,00;COV-1\n10/10/2026;Atenção;50,00;COV-2\n01/09/2026;Crítica;-50,00;COV-3\n");
        $entry = FinancialStatementEntry::query()->withoutGlobalScope('clinic_tenant')->where('external_id', 'COV-1')->firstOrFail();
        $this->post(route('financial-bank-reconciliation.reconciliations.store'), [
            'account_id' => $account->id,
            'entry_id' => $entry->id,
            'transaction_id' => $matched->id,
        ])->assertRedirect();

        $this->get(route('financial-bank-reconciliation.index', [
            'account_id' => $account->id,
            'from' => '2026-09-01',
            'to' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertSee('33,3%')
            ->assertSee('50,0% do valor')
            ->assertSee('0 até 7 dias · 1 de 8 a 30 · 1 acima de 30');

        $this->assertSame(3, FinancialStatementEntry::query()->withoutGlobalScope('clinic_tenant')->count());
        $this->assertSame('paid', $matched->fresh()->status);
    }

    public function test_reversal_trail_is_visible_and_exported_after_the_entry_returns_to_pending(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 10:00:00');
        [$clinic, $user] = $this->context('Clínica Desfazimento', '00000000001113');
        $account = $this->account($clinic, 'Conta desfazimento');
        $transaction = $this->transaction($clinic, 'expense', 'Despesa conciliada', 80, 'paid', '2026-10-20 09:00:00');
        $this->importCsv($user, $account, "data;descricao;valor;id\n20/10/2026;Débito bancário;-80,00;REV-1\n");
        $entry = FinancialStatementEntry::query()->withoutGlobalScope('clinic_tenant')->firstOrFail();
        $this->post(route('financial-bank-reconciliation.reconciliations.store'), [
            'account_id' => $account->id,
            'entry_id' => $entry->id,
            'transaction_id' => $transaction->id,
        ])->assertRedirect();
        $match = FinancialReconciliation::query()->withoutGlobalScope('clinic_tenant')->firstOrFail();
        $reason = 'Extrato vinculado à despesa incorreta.';
        $this->patch(route('financial-bank-reconciliation.reconciliations.reverse', $match->id), [
            'account_id' => $account->id,
            'reason' => $reason,
        ])->assertRedirect();

        $this->get(route('financial-bank-reconciliation.index', [
            'account_id' => $account->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'status' => 'pending',
        ]))
            ->assertOk()
            ->assertSee('Última conciliação desfeita')
            ->assertSee($reason)
            ->assertSee($user->name);

        $csv = $this->get(route('financial-bank-reconciliation.export', [
            'account_id' => $account->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'status' => 'pending',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Conciliação desfeita', $csv);
        $this->assertStringContainsString($reason, $csv);
        $this->assertStringContainsString($user->name, $csv);
    }

    /** @return array{Clinic, User} */
    private function context(string $name, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);

        return [$clinic, $this->userForClinic($clinic)];
    }

    private function userForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'financial.manage'],
            ['name' => 'Financeiro', 'description' => 'Financeiro', 'group' => 'Financeiro', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Financeiro '.Str::random(8),
            'slug' => 'financial-bank-'.Str::lower(Str::random(8)),
            'description' => 'Teste',
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

    private function account(Clinic $clinic, string $name): FinancialAccount
    {
        return FinancialAccount::withoutEvents(fn (): FinancialAccount => FinancialAccount::query()
            ->withoutGlobalScope('clinic_tenant')
            ->create([
                'clinic_id' => $clinic->id,
                'name' => $name,
                'institution' => 'Banco Teste',
                'type' => 'checking',
                'active' => true,
            ]));
    }

    private function transaction(
        Clinic $clinic,
        string $type,
        string $description,
        float $amount,
        string $status,
        ?string $paidAt,
    ): FinancialTransaction {
        return FinancialTransaction::withoutEvents(fn (): FinancialTransaction => FinancialTransaction::query()
            ->withoutGlobalScope('clinic_tenant')
            ->create([
                'clinic_id' => $clinic->id,
                'type' => $type,
                'description' => $description,
                'amount' => $amount,
                'due_date' => $paidAt ? substr($paidAt, 0, 10) : '2026-10-20',
                'paid_at' => $paidAt,
                'status' => $status,
            ]));
    }

    private function importCsv(User $user, FinancialAccount $account, string $contents): void
    {
        $this->actingAs($user)->post(route('financial-bank-reconciliation.imports.store'), [
            'account_id' => $account->id,
            'statement' => UploadedFile::fake()->createWithContent(Str::random(8).'.csv', $contents),
        ])->assertRedirect();
    }
}
