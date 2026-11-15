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

class FinancialManagerialResultTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_result_separates_cash_and_operational_competence_by_category(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 10:00:00');
        [$clinic, $user] = $this->context('Clínica Resultado', '00000000000981');
        $categories = app(FinancialCategoryService::class);
        $categories->ensureDefaults($clinic->id);

        $sales = FinancialCategory::query()->where('system_code', FinancialCategory::CODE_SALES)->firstOrFail();
        $otherIncome = FinancialCategory::query()->where('system_code', FinancialCategory::CODE_OTHER_INCOME)->firstOrFail();
        $purchases = FinancialCategory::query()->where('system_code', FinancialCategory::CODE_INVENTORY_PURCHASES)->firstOrFail();
        $fees = FinancialCategory::query()->where('system_code', FinancialCategory::CODE_CARD_FEES)->firstOrFail();

        $this->transaction($clinic, $sales, 'income', 100, 'paid', '2026-09-30', '2026-10-05 09:00:00');
        $this->transaction($clinic, $otherIncome, 'income', 40, 'pending', '2026-10-20');
        $this->transaction($clinic, $purchases, 'expense', 30, 'paid', '2026-10-12', '2026-11-01 09:00:00');
        $this->transaction($clinic, $fees, 'expense', 5, 'paid', '2026-10-01', '2026-10-10 09:00:00');
        $this->transaction($clinic, $purchases, 'expense', 500, 'cancelled', '2026-10-15');

        [$otherClinic] = $this->context('Clínica Resultado Externa', '00000000000982');
        $categories->ensureDefaults($otherClinic->id);
        $foreignSales = FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $otherClinic->id)
            ->where('system_code', FinancialCategory::CODE_SALES)
            ->firstOrFail();
        $this->transaction($otherClinic, $foreignSales, 'income', 999, 'paid', '2026-10-01', '2026-10-01 09:00:00');

        $this->actingAs($user)->get(route('financial-results.index', [
            'competence' => '2026-10',
            'basis' => 'cash',
        ]))
            ->assertOk()
            ->assertSee('Resultado gerencial mensal')
            ->assertSee('R$ 100,00')
            ->assertSee('R$ 5,00')
            ->assertSee('R$ 95,00')
            ->assertSee('Vendas e serviços')
            ->assertSee('Taxas de cartão')
            ->assertDontSee('R$ 999,00');

        $this->get(route('financial-results.index', [
            'competence' => '2026-10',
            'basis' => 'competence',
        ]))
            ->assertOk()
            ->assertSee('R$ 40,00')
            ->assertSee('R$ 35,00')
            ->assertSee('R$ 5,00')
            ->assertSee('Compras e estoque')
            ->assertDontSee('R$ 500,00')
            ->assertDontSee('R$ 999,00');
    }

    public function test_csv_is_private_formula_safe_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-10-17 10:00:00');
        [$clinic, $user] = $this->context('Clínica CSV', '00000000000983');
        $category = app(FinancialCategoryService::class)->create($clinic->id, [
            'type' => 'income',
            'name' => '=Categoria perigosa',
            'active' => true,
            'sort_order' => 1,
        ]);
        $this->transaction($clinic, $category, 'income', 77.5, 'paid', '2026-10-05', '2026-10-05 09:00:00');

        [$otherClinic] = $this->context('Clínica CSV Externa', '00000000000984');
        $otherCategory = app(FinancialCategoryService::class)->create($otherClinic->id, [
            'type' => 'income',
            'name' => 'Categoria externa',
            'active' => true,
            'sort_order' => 1,
        ]);
        $this->transaction($otherClinic, $otherCategory, 'income', 999, 'paid', '2026-10-05', '2026-10-05 09:00:00');

        $response = $this->actingAs($user)->get(route('financial-results.export', [
            'competence' => '2026-10',
            'basis' => 'cash',
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-robots-tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Categoria perigosa", $csv);
        $this->assertStringContainsString('77,50', $csv);
        $this->assertStringNotContainsString('Categoria externa', $csv);
        $this->assertStringNotContainsString('999,00', $csv);

        $this->from(route('financial-results.index'))
            ->get(route('financial-results.index', ['basis' => 'invalid']))
            ->assertRedirect(route('financial-results.index'))
            ->assertSessionHasErrors('basis');
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
            'slug' => 'financial-result-'.Str::lower(Str::random(8)),
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
