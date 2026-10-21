<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Financial\Services\FinancialCategoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialCashFlowProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_projection_combines_opening_actual_scheduled_and_overdue_values(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Projeção', '00000000001021');
        $income = $this->category($clinic, FinancialCategory::CODE_OTHER_INCOME);
        $expense = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);

        $this->transaction($clinic, $income, 'income', 'Saldo recebido', 1000, 'paid', '2026-10-10', '2026-10-10 09:00:00');
        $this->transaction($clinic, $expense, 'expense', 'Despesa paga anterior', 200, 'paid', '2026-10-15', '2026-10-15 09:00:00');
        $this->transaction($clinic, $income, 'income', 'Entrada realizada', 100, 'paid', '2026-10-20', '2026-10-20 11:00:00');
        $this->transaction($clinic, $expense, 'expense', 'Saída realizada', 50, 'paid', '2026-10-21', '2026-10-21 11:00:00');
        $this->transaction($clinic, $income, 'income', 'Entrada prevista', 300, 'pending', '2026-10-22');
        $this->transaction($clinic, $expense, 'expense', 'Saída prevista', 1500, 'pending', '2026-10-23');
        $this->transaction($clinic, $income, 'income', 'Entrada vencida', 20, 'pending', '2026-10-10');
        $this->transaction($clinic, $expense, 'expense', 'Saída vencida', 40, 'overdue', '2026-10-15');
        $this->transaction($clinic, $expense, 'expense', 'Sem vencimento', 70, 'pending', null);

        $this->actingAs($user)->get(route('financial-transactions.cash-flow', [
            'from' => '2026-10-20',
            'days' => 15,
            'include_overdue' => 1,
        ]))
            ->assertOk()
            ->assertSee('Projeção de fluxo de caixa')
            ->assertSee('R$ 800,00')
            ->assertSee('R$ 420,00')
            ->assertSee('R$ 1.590,00')
            ->assertSee('R$ -370,00')
            ->assertSee('Primeiro em 23/10/2026')
            ->assertSee('Inclui vencidos')
            ->assertSee('20/10 a 24/10')
            ->assertSee('1 lançamento(s) ativo(s) sem vencimento');
    }

    public function test_overdue_values_can_be_visible_without_changing_the_projection(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Cenário', '00000000001022');
        $income = $this->category($clinic, FinancialCategory::CODE_OTHER_INCOME);
        $expense = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);

        $this->transaction($clinic, $income, 'income', 'Saldo recebido', 500, 'paid', '2026-10-01', '2026-10-01 09:00:00');
        $this->transaction($clinic, $expense, 'expense', 'Conta vencida', 100, 'pending', '2026-10-10');
        $this->transaction($clinic, $expense, 'expense', 'Conta futura', 200, 'pending', '2026-10-25');

        $this->actingAs($user)->get(route('financial-transactions.cash-flow', [
            'from' => '2026-10-20',
            'days' => 15,
            'include_overdue' => 0,
        ]))
            ->assertOk()
            ->assertSee('R$ 500,00')
            ->assertSee('R$ 300,00')
            ->assertSee('As contas vencidas estão visíveis nos indicadores, mas não alteram o saldo projetado.')
            ->assertDontSee('Inclui vencidos');
    }

    public function test_daily_composition_is_exact_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Composição', '00000000001023');
        $expense = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($clinic, $expense, 'expense', 'Vencida da clínica', 40, 'pending', '2026-10-10');
        $this->transaction($clinic, $expense, 'expense', 'Prevista do dia', 60, 'pending', '2026-10-20');
        $this->transaction($clinic, $expense, 'expense', 'Prevista futura', 80, 'pending', '2026-10-21');

        [$foreignClinic] = $this->context('Clínica Composição Externa', '00000000001024');
        $foreignExpense = $this->category($foreignClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($foreignClinic, $foreignExpense, 'expense', 'Conta externa', 999, 'pending', '2026-10-20');

        $this->actingAs($user)->get(route('financial-transactions.cash-flow.transactions', [
            'from' => '2026-10-20',
            'days' => 15,
            'include_overdue' => 1,
            'date' => '2026-10-20',
        ]))
            ->assertOk()
            ->assertSee('Vencida da clínica')
            ->assertSee('Vencido assumido na data inicial')
            ->assertSee('Prevista do dia')
            ->assertDontSee('Prevista futura')
            ->assertDontSee('Conta externa')
            ->assertDontSee('R$ 999,00');

        $this->get(route('financial-transactions.cash-flow.transactions', [
            'from' => '2026-10-20',
            'days' => 15,
            'date' => '2026-11-20',
        ]))->assertNotFound();
    }

    public function test_csv_is_private_formula_safe_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica CSV Caixa', '00000000001025');
        $expense = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($clinic, $expense, 'expense', '=Descrição perigosa', 42.5, 'pending', '2026-10-25');

        [$foreignClinic] = $this->context('Clínica CSV Caixa Externa', '00000000001026');
        $foreignExpense = $this->category($foreignClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($foreignClinic, $foreignExpense, 'expense', 'Conta externa CSV', 999, 'pending', '2026-10-25');

        $response = $this->actingAs($user)->get(route('financial-transactions.cash-flow.export', [
            'from' => '2026-10-20',
            'days' => 15,
            'include_overdue' => 1,
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-robots-tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Descrição perigosa", $csv);
        $this->assertStringContainsString('42,50', $csv);
        $this->assertStringNotContainsString('Conta externa CSV', $csv);
        $this->assertStringNotContainsString('999,00', $csv);
    }

    public function test_global_selection_and_filter_validation_do_not_mix_clinics(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$firstClinic, $user] = $this->context('Clínica Caixa Global A', '00000000001027');
        [$secondClinic] = $this->context('Clínica Caixa Global B', '00000000001028');
        $user->forceFill(['clinic_id' => null])->save();

        $firstExpense = $this->category($firstClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $secondExpense = $this->category($secondClinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($firstClinic, $firstExpense, 'expense', 'Conta clínica A', 10, 'pending', '2026-10-21');
        $this->transaction($secondClinic, $secondExpense, 'expense', 'Conta clínica B', 20, 'pending', '2026-10-21');

        $this->actingAs($user)->get(route('financial-transactions.cash-flow.transactions', [
            'clinic_id' => $secondClinic->id,
            'from' => '2026-10-20',
            'days' => 15,
            'date' => '2026-10-21',
        ]))
            ->assertOk()
            ->assertSee('Conta clínica B')
            ->assertDontSee('Conta clínica A');

        $this->from(route('financial-transactions.cash-flow'))
            ->get(route('financial-transactions.cash-flow', [
                'from' => '20/10/2026',
                'days' => 45,
                'include_overdue' => 'invalid',
            ]))
            ->assertRedirect(route('financial-transactions.cash-flow'))
            ->assertSessionHasErrors(['from', 'days', 'include_overdue']);
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
            'slug' => 'financial-cash-flow-'.Str::lower(Str::random(8)),
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
