<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Financial\Services\FinancialCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->clinic('Clínica Financeira', '00000000000801');
        $this->user = $this->userForClinic($this->clinic, ['financial.manage']);
    }

    public function test_finance_creates_default_categories_once_and_keeps_them_inside_the_clinic(): void
    {
        $this->actingAs($this->user)->get(route('financial-categories.index'))
            ->assertOk()
            ->assertSee('Vendas e serviços')
            ->assertSee('Compras e estoque')
            ->assertSee('Taxas de cartão');

        $this->get(route('financial-categories.index'))->assertOk();

        $this->assertSame(8, FinancialCategory::query()->count());
        $this->assertSame(
            8,
            FinancialCategory::query()->where('clinic_id', $this->clinic->id)->distinct('system_code')->count('system_code')
        );

        $this->post(route('financial-categories.store'), [
            'type' => 'expense',
            'name' => 'Aluguel',
            'active' => '1',
            'sort_order' => '15',
        ])->assertRedirect(route('financial-categories.index'));

        $this->assertDatabaseHas('financial_categories', [
            'clinic_id' => $this->clinic->id,
            'type' => 'expense',
            'name' => 'Aluguel',
            'active' => true,
            'system_code' => null,
        ]);

        $otherClinic = $this->clinic('Clínica Vizinha', '00000000000802');
        app(FinancialCategoryService::class)->ensureDefaults($otherClinic->id);

        $this->assertSame(9, FinancialCategory::query()->count());
        $this->assertSame(
            8,
            FinancialCategory::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $otherClinic->id)
                ->count()
        );
    }

    public function test_category_type_is_immutable_but_name_status_and_order_can_change(): void
    {
        $category = app(FinancialCategoryService::class)->create($this->clinic->id, [
            'type' => 'expense',
            'name' => 'Aluguel',
            'active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($this->user)
            ->put(route('financial-categories.update', $category->id), [
                'type' => 'income',
                'name' => 'Aluguel e condomínio',
                'active' => '0',
                'sort_order' => '25',
            ])
            ->assertRedirect(route('financial-categories.index'))
            ->assertSessionDoesntHaveErrors();

        $category->refresh();
        $this->assertSame('expense', $category->type);
        $this->assertSame('Aluguel e condomínio', $category->name);
        $this->assertFalse($category->active);
        $this->assertSame(25, $category->sort_order);
    }

    public function test_transactions_only_accept_matching_categories_from_the_current_clinic(): void
    {
        $service = app(FinancialCategoryService::class);
        $service->ensureDefaults($this->clinic->id);
        $income = FinancialCategory::query()->where('system_code', FinancialCategory::CODE_SALES)->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('financial-transactions.create'))
            ->post(route('financial-transactions.store'), $this->transactionPayload('expense', $income->id, 'Categoria de tipo errado'))
            ->assertRedirect(route('financial-transactions.create'))
            ->assertSessionHasErrors('financial_category_id');

        $otherClinic = $this->clinic('Clínica Externa', '00000000000803');
        $service->ensureDefaults($otherClinic->id);
        $foreignExpense = FinancialCategory::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $otherClinic->id)
            ->where('system_code', FinancialCategory::CODE_OTHER_EXPENSES)
            ->firstOrFail();

        $this->post(route('financial-transactions.store'), $this->transactionPayload('expense', $foreignExpense->id, 'Categoria externa'))
            ->assertSessionHasErrors('financial_category_id');

        $this->post(route('financial-transactions.store'), $this->transactionPayload('income', $income->id, 'Receita classificada'))
            ->assertSessionDoesntHaveErrors();

        $this->post(route('financial-transactions.store'), $this->transactionPayload('expense', null, 'Despesa automática'))
            ->assertSessionDoesntHaveErrors();

        $classified = FinancialTransaction::query()->where('description', 'Receita classificada')->firstOrFail();
        $automatic = FinancialTransaction::query()->where('description', 'Despesa automática')->firstOrFail();

        $this->assertSame($income->id, $classified->financial_category_id);
        $this->assertSame(
            FinancialCategory::CODE_OTHER_EXPENSES,
            $automatic->category()->value('system_code')
        );

        $this->get(route('financial-transactions.index', ['financial_category_id' => $income->id]))
            ->assertOk()
            ->assertSee('Receita classificada')
            ->assertDontSee('Despesa automática');
    }

    /** @return array<string, mixed> */
    private function transactionPayload(string $type, ?int $categoryId, string $description): array
    {
        return [
            'type' => $type,
            'financial_category_id' => $categoryId,
            'description' => $description,
            'amount' => '100.00',
            'due_date' => today()->toDateString(),
            'status' => 'pending',
        ];
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

    /** @param array<int, string> $permissionSlugs */
    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Test role '.Str::random(6),
            'slug' => 'test-role-'.Str::lower(Str::random(8)),
            'description' => 'Test role',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Test permission',
                    'group' => 'Tests',
                    'active' => true,
                ]
            );
            $role->permissions()->attach($permission->id);
        }

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
