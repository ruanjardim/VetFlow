<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Financial\Services\FinancialCategoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialObligationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_center_summarizes_due_dates_and_keeps_clinics_isolated(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Vencimentos', '00000000001001');
        $category = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);

        $this->transaction($clinic, $category, 'expense', 'Conta vencida', 100, 'pending', '2026-10-10');
        $this->transaction($clinic, $category, 'expense', 'Conta de hoje', 50, 'overdue', '2026-10-20');
        $this->transaction($clinic, $category, 'income', 'Receber depois', 70, 'pending', '2026-11-05');
        $this->transaction($clinic, $category, 'expense', 'Conta paga', 20, 'paid', '2026-10-15', '2026-10-15 09:00:00');
        $this->transaction($clinic, $category, 'expense', 'Conta cancelada', 999, 'cancelled', '2026-10-18');
        $this->transaction($clinic, $category, 'expense', 'Sem vencimento', 10, 'pending', null);

        [$foreignClinic] = $this->context('Clínica Vencimentos Externa', '00000000001002');
        $foreignCategory = $this->category($foreignClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($foreignClinic, $foreignCategory, 'expense', 'Conta externa', 888, 'pending', '2026-10-10');

        $this->actingAs($user)->get(route('financial-obligations.index', [
            'from' => '2026-10-01',
            'to' => '2026-11-30',
            'type' => 'all',
            'status' => 'all',
        ]))
            ->assertOk()
            ->assertSee('Contas a pagar e receber')
            ->assertSee('R$ 220,00')
            ->assertSee('R$ 100,00')
            ->assertSee('R$ 50,00')
            ->assertSee('R$ 70,00')
            ->assertSee('R$ 20,00')
            ->assertSee('Conta vencida')
            ->assertSee('Conta de hoje')
            ->assertSee('Receber depois')
            ->assertSee('Conta paga')
            ->assertSee('Conta cancelada')
            ->assertSee('Há 1 lançamento(s) ativo(s) sem data de vencimento')
            ->assertDontSee('Conta externa')
            ->assertDontSee('R$ 888,00');
    }

    public function test_type_and_computed_status_filters_are_validated(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Filtros', '00000000001003');
        $expense = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $income = $this->category($clinic, FinancialCategory::CODE_OTHER_INCOME);

        $this->transaction($clinic, $expense, 'expense', 'Despesa vencida', 30, 'pending', '2026-10-05');
        $this->transaction($clinic, $expense, 'expense', 'Despesa futura', 40, 'pending', '2026-10-25');
        $this->transaction($clinic, $income, 'income', 'Receita vencida', 50, 'pending', '2026-10-05');

        $this->actingAs($user)->get(route('financial-obligations.index', [
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'type' => 'expense',
            'status' => 'overdue',
        ]))
            ->assertOk()
            ->assertSee('Despesa vencida')
            ->assertDontSee('Despesa futura')
            ->assertDontSee('Receita vencida');

        $this->from(route('financial-obligations.index'))
            ->get(route('financial-obligations.index', [
                'from' => '2026-10-31',
                'to' => '2026-10-01',
                'type' => 'invalid',
                'status' => 'invalid',
                'aging' => 'invalid',
            ]))
            ->assertRedirect(route('financial-obligations.index'))
            ->assertSessionHasErrors(['to', 'type', 'status', 'aging']);
    }

    public function test_aging_buckets_link_to_the_exact_overdue_composition(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Faixas', '00000000001006');
        $category = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);

        $this->transaction($clinic, $category, 'expense', 'Atraso dois dias', 10, 'pending', '2026-10-18');
        $this->transaction($clinic, $category, 'expense', 'Atraso dez dias', 20, 'pending', '2026-10-10');
        $this->transaction($clinic, $category, 'expense', 'Atraso vinte dias', 30, 'pending', '2026-09-30');
        $this->transaction($clinic, $category, 'expense', 'Atraso quarenta e nove dias', 40, 'pending', '2026-09-01');
        $this->transaction($clinic, $category, 'expense', 'Atraso cento e onze dias', 50, 'pending', '2026-07-01');

        $this->actingAs($user)->get(route('financial-obligations.index', [
            'from' => '2026-01-01',
            'to' => '2026-10-31',
            'status' => 'overdue',
        ]))
            ->assertOk()
            ->assertSee('Até 5 dias')
            ->assertSee('6 a 15 dias')
            ->assertSee('16 a 30 dias')
            ->assertSee('31 a 60 dias')
            ->assertSee('Acima de 60 dias')
            ->assertSee('R$ 10,00')
            ->assertSee('R$ 20,00')
            ->assertSee('R$ 30,00')
            ->assertSee('R$ 40,00')
            ->assertSee('R$ 50,00');

        $this->get(route('financial-obligations.index', [
            'from' => '2026-01-01',
            'to' => '2026-10-31',
            'type' => 'expense',
            'status' => 'overdue',
            'aging' => '6_15',
        ]))
            ->assertOk()
            ->assertSee('A composição abaixo está limitada à faixa')
            ->assertSee('Atraso dez dias')
            ->assertDontSee('Atraso dois dias')
            ->assertDontSee('Atraso vinte dias')
            ->assertDontSee('Atraso quarenta e nove dias')
            ->assertDontSee('Atraso cento e onze dias');
    }

    public function test_csv_is_private_formula_safe_filtered_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Exportação', '00000000001007');
        $category = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($clinic, $category, 'expense', '=Descrição perigosa', 42.5, 'pending', '2026-10-10');
        $this->transaction($clinic, $category, 'expense', 'Conta futura', 55, 'pending', '2026-10-25');

        [$foreignClinic] = $this->context('Clínica Exportação Externa', '00000000001008');
        $foreignCategory = $this->category($foreignClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($foreignClinic, $foreignCategory, 'expense', 'Conta externa CSV', 999, 'pending', '2026-10-10');

        $response = $this->actingAs($user)->get(route('financial-obligations.export', [
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'type' => 'expense',
            'status' => 'overdue',
            'aging' => '6_15',
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-robots-tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Descrição perigosa", $csv);
        $this->assertStringContainsString('42,50', $csv);
        $this->assertStringNotContainsString('Conta futura', $csv);
        $this->assertStringNotContainsString('Conta externa CSV', $csv);
        $this->assertStringNotContainsString('999,00', $csv);
    }

    public function test_reviews_are_append_only_validated_and_clinic_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Revisão', '00000000001009');
        $category = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($clinic, $category, 'expense', 'Conta para revisar', 80, 'pending', '2026-10-10');
        $filters = [
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'type' => 'expense',
            'status' => 'overdue',
            'aging' => '6_15',
        ];

        $this->actingAs($user)->post(route('financial-obligations.reviews.store'), $filters + [
            'decision' => 'reviewed',
            'confirm_review' => '1',
        ])->assertRedirect(route('financial-obligations.index', $filters));

        CarbonImmutable::setTestNow('2026-10-20 11:00:00');
        $this->post(route('financial-obligations.reviews.store'), $filters + [
            'decision' => 'follow_up',
            'note' => 'Confirmar o pagamento com o fornecedor responsável.',
            'confirm_review' => '1',
        ])->assertRedirect(route('financial-obligations.index', $filters));

        $this->assertSame(2, AuditEvent::query()
            ->where('clinic_id', $clinic->id)
            ->where('event', 'financial.obligations.reviewed')
            ->count());

        $this->get(route('financial-obligations.index', $filters))
            ->assertOk()
            ->assertSee('Revisado')
            ->assertSee('Requer acompanhamento')
            ->assertSee('Confirmar o pagamento com o fornecedor responsável.');

        $this->from(route('financial-obligations.index', $filters))
            ->post(route('financial-obligations.reviews.store'), $filters + [
                'decision' => 'follow_up',
            ])
            ->assertRedirect(route('financial-obligations.index', $filters))
            ->assertSessionHasErrors(['note', 'confirm_review']);
    }

    public function test_global_user_can_select_one_active_clinic_without_mixing_data(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$firstClinic, $user] = $this->context('Clínica Global A', '00000000001004');
        [$secondClinic] = $this->context('Clínica Global B', '00000000001005');
        $user->forceFill(['clinic_id' => null])->save();

        $firstCategory = $this->category($firstClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $secondCategory = $this->category($secondClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($firstClinic, $firstCategory, 'expense', 'Conta clínica A', 10, 'pending', '2026-10-21');
        $this->transaction($secondClinic, $secondCategory, 'expense', 'Conta clínica B', 20, 'pending', '2026-10-21');

        $this->actingAs($user)->get(route('financial-obligations.index', [
            'clinic_id' => $secondClinic->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'status' => 'all',
        ]))
            ->assertOk()
            ->assertSee('Estabelecimento')
            ->assertSee('Conta clínica B')
            ->assertDontSee('Conta clínica A');
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
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'financial.manage'],
            ['name' => 'Financeiro', 'description' => 'Financeiro', 'group' => 'Financeiro', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Financeiro '.Str::random(8),
            'slug' => 'financial-obligation-'.Str::lower(Str::random(8)),
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

        return [$clinic, $user];
    }

    private function category(Clinic $clinic, string $code): FinancialCategory
    {
        app(FinancialCategoryService::class)->ensureDefaults($clinic->id);

        return FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinic->id)
            ->where('system_code', $code)
            ->firstOrFail();
    }

    private function transaction(
        Clinic $clinic,
        FinancialCategory $category,
        string $type,
        string $description,
        float $amount,
        string $status,
        ?string $dueDate,
        ?string $paidAt = null,
    ): FinancialTransaction {
        return FinancialTransaction::query()->withoutGlobalScope('clinic_tenant')->create([
            'clinic_id' => $clinic->id,
            'financial_category_id' => $category->id,
            'type' => $type,
            'description' => $description,
            'amount' => $amount,
            'due_date' => $dueDate,
            'paid_at' => $paidAt,
            'status' => $status,
        ]);
    }
}
