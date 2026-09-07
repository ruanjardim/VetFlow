<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\PetShopServices\Models\PetShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesQuickPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sale_opens_the_quick_petshop_pdv_with_only_active_tenant_services(): void
    {
        $clinic = $this->clinic('PetShop Piloto', '00000000001001');
        $otherClinic = $this->clinic('Outra Clinica', '00000000001002');

        PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Banho completo',
            'category' => 'Banho e Tosa',
            'base_price' => 50,
            'small_price' => 40,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Servico inativo',
            'base_price' => 35,
            'active' => false,
        ]);

        PetShopService::query()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Servico de outra clinica',
            'base_price' => 70,
            'active' => true,
        ]);

        $response = $this->actingAs($this->salesUser($clinic))
            ->get(route('sales.create'));

        $response
            ->assertOk()
            ->assertSee('PDV rapido PetShop')
            ->assertSee('Banho completo')
            ->assertSee('Porte pequeno - R$ 40,00')
            ->assertSee('data-sale-quick-item', false)
            ->assertSee('name="source" value="petshop_pdv"', false)
            ->assertDontSee('Servico inativo')
            ->assertDontSee('Servico de outra clinica');
    }

    public function test_advanced_sale_form_remains_available(): void
    {
        $clinic = $this->clinic('Clinica Venda Avancada', '00000000001003');

        $response = $this->actingAs($this->salesUser($clinic))
            ->get(route('sales.create', ['mode' => 'advanced']));

        $response
            ->assertOk()
            ->assertSee('Nova venda')
            ->assertSee('Status')
            ->assertSee('Voltar ao PDV rapido')
            ->assertDontSee('data-sale-quick-item', false);
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

    private function salesUser(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'sales.manage'],
            [
                'name' => 'Gerenciar PDV e vendas',
                'description' => 'Permissao de teste do PDV.',
                'group' => 'Tests',
                'active' => true,
            ]
        );
        $role = Role::query()->create([
            'name' => 'Operador PDV '.Str::random(6),
            'slug' => 'operador-pdv-'.Str::lower(Str::random(8)),
            'description' => 'Role de teste do PDV.',
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
