<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialBudget;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Financial\Services\FinancialCategoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialBudgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_budget_compares_planned_and_realized_by_category_without_crossing_tenants(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 10:00:00');
        [$clinic, $user] = $this->context('Clínica Orçamento', '00000000000991');
        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($clinic->id);
        $sales = $this->category($clinic, FinancialCategory::CODE_SALES);
        $purchases = $this->category($clinic, FinancialCategory::CODE_INVENTORY_PURCHASES);
        $otherExpenses = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);

        $this->budget($clinic, $sales, 200);
        $this->budget($clinic, $purchases, 70);
        $this->transaction($clinic, $sales, 'income', 150, 'paid', '2026-10-05', '2026-10-05 09:00:00');
        $this->transaction($clinic, $purchases, 'expense', 80, 'paid', '2026-10-06', '2026-10-06 09:00:00');
        $this->transaction($clinic, $otherExpenses, 'expense', 15, 'paid', '2026-10-07', '2026-10-07 09:00:00');

        [$otherClinic] = $this->context('Clínica Orçamento Externa', '00000000000992');
        $categories->ensureDefaults($otherClinic->id);
        $foreignSales = $this->category($otherClinic, FinancialCategory::CODE_SALES);
        $this->budget($otherClinic, $foreignSales, 999);
        $this->transaction($otherClinic, $foreignSales, 'income', 999, 'paid', '2026-10-05', '2026-10-05 09:00:00');

        $this->actingAs($user)->get(route('financial-budgets.index', [
            'competence' => '2026-10',
            'basis' => 'cash',
        ]))
            ->assertOk()
            ->assertSee('Orçamento mensal')
            ->assertSee('R$ 200,00')
            ->assertSee('R$ 150,00')
            ->assertSee('R$ 70,00')
            ->assertSee('R$ 80,00')
            ->assertSee('Abaixo do previsto')
            ->assertSee('Acima do previsto')
            ->assertSee('Alertas do orçamento')
            ->assertSee('R$ 10,00 acima')
            ->assertSee('Despesa sem orçamento definido')
            ->assertSee('R$ 15,00 acima')
            ->assertDontSee('R$ 999,00');
    }

    public function test_budget_save_normalizes_values_audits_changes_and_rejects_foreign_categories(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 10:00:00');
        [$clinic, $user] = $this->context('Clínica Salvar Orçamento', '00000000000993');
        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($clinic->id);
        $sales = $this->category($clinic, FinancialCategory::CODE_SALES);
        $otherIncome = $this->category($clinic, FinancialCategory::CODE_OTHER_INCOME);
        $purchases = $this->category($clinic, FinancialCategory::CODE_INVENTORY_PURCHASES);

        $payload = [
            'competence' => '2026-10',
            'basis' => 'competence',
            'budgets' => [
                $sales->id => '1.234,56',
                $otherIncome->id => '0,00',
                $purchases->id => '250,00',
            ],
        ];

        $this->actingAs($user)->put(route('financial-budgets.update'), $payload)
            ->assertRedirect(route('financial-budgets.index', [
                'competence' => '2026-10',
                'basis' => 'competence',
            ]))
            ->assertSessionHas('success');

        $this->assertSame('1234.56', FinancialBudget::query()
            ->where('financial_category_id', $sales->id)
            ->whereDate('competence', '2026-10-01')
            ->value('amount'));
        $this->assertSame('250.00', FinancialBudget::query()
            ->where('financial_category_id', $purchases->id)
            ->whereDate('competence', '2026-10-01')
            ->value('amount'));
        $this->assertSame(2, AuditEvent::query()->where('event', 'financial.budget.updated')->count());

        $this->put(route('financial-budgets.update'), $payload)->assertSessionHas('success');
        $this->assertSame(2, FinancialBudget::query()->count());
        $this->assertSame(2, AuditEvent::query()->where('event', 'financial.budget.updated')->count());

        [$otherClinic] = $this->context('Clínica Categoria Externa', '00000000000994');
        $categories->ensureDefaults($otherClinic->id);
        $foreignCategory = $this->category($otherClinic, FinancialCategory::CODE_SALES);

        $this->from(route('financial-budgets.index', ['competence' => '2026-10', 'basis' => 'cash']))
            ->put(route('financial-budgets.update'), [
                'competence' => '2026-10',
                'basis' => 'cash',
                'budgets' => [$foreignCategory->id => '999,00'],
            ])
            ->assertRedirect(route('financial-budgets.index', ['competence' => '2026-10', 'basis' => 'cash']))
            ->assertSessionHasErrors('budgets');

        $this->assertDatabaseMissing('financial_budgets', [
            'clinic_id' => $clinic->id,
            'financial_category_id' => $foreignCategory->id,
        ]);
    }

    public function test_global_user_can_open_default_clinic_and_explicitly_select_another_one(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 10:00:00');
        [$firstClinic, $user] = $this->context('Clínica Global A', '00000000000995');
        [$secondClinic] = $this->context('Clínica Global B', '00000000000996');
        $user->forceFill(['clinic_id' => null])->save();

        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($firstClinic->id);
        $categories->ensureDefaults($secondClinic->id);
        $firstSales = $this->category($firstClinic, FinancialCategory::CODE_SALES);
        $secondSales = $this->category($secondClinic, FinancialCategory::CODE_SALES);
        $this->budget($firstClinic, $firstSales, 111);
        $this->budget($secondClinic, $secondSales, 222);

        $this->actingAs($user)->get(route('financial-budgets.index'))
            ->assertOk()
            ->assertSee('Clínica Global A')
            ->assertSee('Clínica Global B');

        $this->get(route('financial-budgets.index', [
            'competence' => '2026-10',
            'basis' => 'cash',
            'clinic_id' => $secondClinic->id,
        ]))
            ->assertOk()
            ->assertSee('R$ 222,00')
            ->assertDontSee('R$ 111,00');
    }

    public function test_copy_previous_budget_preserves_existing_values_and_audits_only_copied_categories(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 10:00:00');
        [$clinic, $user] = $this->context('Clínica Copiar Orçamento', '00000000000997');
        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($clinic->id);
        $sales = $this->category($clinic, FinancialCategory::CODE_SALES);
        $purchases = $this->category($clinic, FinancialCategory::CODE_INVENTORY_PURCHASES);

        $this->budget($clinic, $sales, 200, '2026-09-01');
        $this->budget($clinic, $purchases, 70, '2026-09-01');
        $this->budget($clinic, $sales, 999, '2026-10-01');

        [$otherClinic] = $this->context('Clínica Copiar Orçamento Externa', '00000000000998');
        $categories->ensureDefaults($otherClinic->id);
        $foreignPurchases = $this->category($otherClinic, FinancialCategory::CODE_INVENTORY_PURCHASES);
        $this->budget($otherClinic, $foreignPurchases, 777, '2026-09-01');

        $this->actingAs($user)->post(route('financial-budgets.copy-previous'), [
            'competence' => '2026-10',
            'basis' => 'cash',
        ])
            ->assertRedirect(route('financial-budgets.index', [
                'competence' => '2026-10',
                'basis' => 'cash',
            ]))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 valor copiado'));

        $this->assertSame('999.00', FinancialBudget::query()
            ->where('financial_category_id', $sales->id)
            ->whereDate('competence', '2026-10-01')
            ->value('amount'));
        $this->assertSame('70.00', FinancialBudget::query()
            ->where('financial_category_id', $purchases->id)
            ->whereDate('competence', '2026-10-01')
            ->value('amount'));
        $this->assertDatabaseMissing('financial_budgets', [
            'clinic_id' => $clinic->id,
            'financial_category_id' => $foreignPurchases->id,
            'competence' => '2026-10-01',
        ]);
        $this->assertSame(1, AuditEvent::query()->where('event', 'financial.budget.copied')->count());
        $this->assertSame('2026-09', AuditEvent::query()
            ->where('event', 'financial.budget.copied')
            ->firstOrFail()
            ->metadata['source_competence']);
    }

    public function test_copy_previous_budget_warns_when_source_month_has_no_values(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 10:00:00');
        [, $user] = $this->context('Clínica Sem Orçamento Anterior', '00000000000999');

        $this->actingAs($user)->post(route('financial-budgets.copy-previous'), [
            'competence' => '2026-10',
            'basis' => 'cash',
        ])
            ->assertRedirect(route('financial-budgets.index', [
                'competence' => '2026-10',
                'basis' => 'cash',
            ]))
            ->assertSessionHas('warning', 'O mês anterior não possui valores de orçamento para copiar.');
    }

    public function test_budget_csv_is_private_formula_safe_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Exportar Orçamento', '00000000001001');
        $category = app(FinancialCategoryService::class)->create($clinic->id, [
            'type' => 'expense',
            'name' => '=Despesa perigosa',
            'active' => true,
            'sort_order' => 5,
        ]);
        $this->budget($clinic, $category, 123.45);
        $this->transaction($clinic, $category, 'expense', 150.75, 'paid', '2026-10-10', '2026-10-10 12:00:00');

        [$otherClinic] = $this->context('Clínica Exportar Externa', '00000000001002');
        $foreignCategory = app(FinancialCategoryService::class)->create($otherClinic->id, [
            'type' => 'expense',
            'name' => 'Despesa externa',
            'active' => true,
            'sort_order' => 5,
        ]);
        $this->budget($otherClinic, $foreignCategory, 999);
        $this->transaction($otherClinic, $foreignCategory, 'expense', 999, 'paid', '2026-10-10', '2026-10-10 12:00:00');

        $response = $this->actingAs($user)->get(route('financial-budgets.export', [
            'competence' => '2026-10',
            'basis' => 'cash',
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-robots-tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Despesa perigosa", $csv);
        $this->assertStringContainsString('123,45', $csv);
        $this->assertStringContainsString('150,75', $csv);
        $this->assertStringNotContainsString('Despesa externa', $csv);
        $this->assertStringNotContainsString('999,00', $csv);
    }

    public function test_budget_transaction_composition_uses_the_exact_basis_month_category_and_tenant(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Composição Orçamento', '00000000001003');
        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($clinic->id);
        $purchases = $this->category($clinic, FinancialCategory::CODE_INVENTORY_PURCHASES);
        $otherExpenses = $this->category($clinic, FinancialCategory::CODE_OTHER_EXPENSES);
        $this->transaction($clinic, $purchases, 'expense', 80, 'paid', '2026-10-06', '2026-10-06 09:00:00');
        $this->transaction($clinic, $purchases, 'expense', 81, 'pending', '2026-10-07');
        $this->transaction($clinic, $purchases, 'expense', 82, 'paid', '2026-09-30', '2026-09-30 09:00:00');
        $this->transaction($clinic, $otherExpenses, 'expense', 83, 'paid', '2026-10-08', '2026-10-08 09:00:00');

        [$otherClinic] = $this->context('Clínica Composição Externa', '00000000001004');
        $categories->ensureDefaults($otherClinic->id);
        $foreignPurchases = $this->category($otherClinic, FinancialCategory::CODE_INVENTORY_PURCHASES);
        $this->transaction($otherClinic, $foreignPurchases, 'expense', 999, 'paid', '2026-10-06', '2026-10-06 09:00:00');

        $this->actingAs($user)->get(route('financial-budgets.transactions', [
            'competence' => '2026-10',
            'basis' => 'cash',
            'type' => 'expense',
            'financial_category_id' => $purchases->id,
        ]))
            ->assertOk()
            ->assertSee('Composição do realizado')
            ->assertSee('R$ 80,00')
            ->assertDontSee('R$ 81,00')
            ->assertDontSee('R$ 82,00')
            ->assertDontSee('R$ 83,00')
            ->assertDontSee('R$ 999,00');

        $this->get(route('financial-budgets.transactions', [
            'competence' => '2026-10',
            'basis' => 'competence',
            'type' => 'expense',
            'financial_category_id' => $purchases->id,
        ]))
            ->assertOk()
            ->assertSee('R$ 80,00')
            ->assertSee('R$ 81,00')
            ->assertDontSee('R$ 82,00');

        $this->get(route('financial-budgets.transactions', [
            'competence' => '2026-10',
            'basis' => 'cash',
            'type' => 'expense',
            'financial_category_id' => $foreignPurchases->id,
        ]))->assertNotFound();
    }

    public function test_budget_screen_shows_twelve_month_history_and_scoped_audit_events(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:00:00');
        [$clinic, $user] = $this->context('Clínica Histórico Orçamento', '00000000001005');
        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($clinic->id);
        $sales = $this->category($clinic, FinancialCategory::CODE_SALES);
        $this->budget($clinic, $sales, 321.45, '2026-09-01');
        $this->transaction($clinic, $sales, 'income', 300.25, 'paid', '2026-09-15', '2026-09-15 09:00:00');

        $this->actingAs($user)->put(route('financial-budgets.update'), [
            'competence' => '2026-10',
            'basis' => 'cash',
            'budgets' => [$sales->id => '500,00'],
        ])->assertSessionHas('success');

        [$otherClinic, $otherUser] = $this->context('Clínica Histórico Externa', '00000000001006');
        $categories->ensureDefaults($otherClinic->id);
        $foreignSales = $this->category($otherClinic, FinancialCategory::CODE_SALES);
        $this->actingAs($otherUser)->put(route('financial-budgets.update'), [
            'competence' => '2026-10',
            'basis' => 'cash',
            'budgets' => [$foreignSales->id => '999,00'],
        ])->assertSessionHas('success');

        $this->actingAs($user)->get(route('financial-budgets.index', [
            'competence' => '2026-10',
            'basis' => 'cash',
        ]))
            ->assertOk()
            ->assertSee('Histórico de 12 meses')
            ->assertSee('09/2026')
            ->assertSee('R$ 321,45')
            ->assertSee('R$ 300,25')
            ->assertSee('Alterações do orçamento')
            ->assertSee('Valor atualizado')
            ->assertSee($user->name)
            ->assertDontSee('R$ 999,00');
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
            'slug' => 'financial-budget-'.Str::lower(Str::random(8)),
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
        return FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinic->id)
            ->where('system_code', $code)
            ->firstOrFail();
    }

    private function budget(
        Clinic $clinic,
        FinancialCategory $category,
        float $amount,
        string $competence = '2026-10-01'
    ): FinancialBudget {
        return FinancialBudget::query()->withoutGlobalScope('clinic_tenant')->create([
            'clinic_id' => $clinic->id,
            'financial_category_id' => $category->id,
            'competence' => $competence,
            'amount' => $amount,
        ]);
    }

    private function transaction(
        Clinic $clinic,
        FinancialCategory $category,
        string $type,
        float $amount,
        string $status,
        ?string $dueDate,
        ?string $paidAt = null,
    ): FinancialTransaction {
        return FinancialTransaction::query()->withoutGlobalScope('clinic_tenant')->create([
            'clinic_id' => $clinic->id,
            'financial_category_id' => $category->id,
            'type' => $type,
            'description' => $category->name,
            'amount' => $amount,
            'due_date' => $dueDate,
            'paid_at' => $paidAt,
            'status' => $status,
        ]);
    }
}
