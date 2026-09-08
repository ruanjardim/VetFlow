<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Sales\Models\Sale;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
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

    public function test_operator_can_create_and_select_a_tutor_and_pet_from_the_quick_pdv(): void
    {
        $clinic = $this->clinic('PetShop Cadastro Rapido', '00000000001004');
        $user = $this->salesUser($clinic, [
            'sales.manage',
            'tutors.manage',
            'patients.manage',
        ]);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee(route('tutores.create'), false)
            ->assertSee(route('patients.create'), false)
            ->assertSee('Cadastrar responsável e pet sem sair do PDV');

        $response = $this->actingAs($user)
            ->postJson(route('sales.quick-customer.store'), [
                'clinic_id' => $clinic->id,
                'tutor_name' => 'Maria do Banho',
                'tutor_phone' => '(21) 99999-0000',
                'tutor_email' => 'maria@example.com',
                'patient_name' => 'Pingo',
                'patient_species' => 'Canino',
                'patient_breed' => 'Shih-tzu',
                'patient_weight' => 6.4,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('tutor.name', 'Maria do Banho')
            ->assertJsonPath('patient.name', 'Pingo');

        $tutor = Tutor::query()->where('name', 'Maria do Banho')->firstOrFail();
        $patient = Patient::query()->where('name', 'Pingo')->firstOrFail();

        $this->assertSame($clinic->id, $tutor->clinic_id);
        $this->assertSame($clinic->id, $patient->clinic_id);
        $this->assertSame($tutor->id, $patient->tutor_id);
        $this->assertSame('Canino', $patient->species);
    }

    public function test_quick_customer_creation_requires_tutor_and_patient_permissions(): void
    {
        $clinic = $this->clinic('PetShop Sem Cadastro', '00000000001005');

        $this->actingAs($this->salesUser($clinic))
            ->postJson(route('sales.quick-customer.store'), [
                'clinic_id' => $clinic->id,
                'tutor_name' => 'Responsável Bloqueado',
                'tutor_phone' => '21999990000',
                'patient_name' => 'Pet Bloqueado',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tutors', ['name' => 'Responsável Bloqueado']);
        $this->assertDatabaseMissing('patients', ['name' => 'Pet Bloqueado']);
    }

    public function test_global_operator_must_choose_the_clinic_for_quick_customer_creation(): void
    {
        $clinic = $this->clinic('PetShop Global', '00000000001006');
        $user = $this->salesUser(null, [
            'sales.manage',
            'tutors.manage',
            'patients.manage',
        ]);

        $this->actingAs($user)
            ->postJson(route('sales.quick-customer.store'), [
                'tutor_name' => 'Responsável Global',
                'tutor_phone' => '21999990001',
                'patient_name' => 'Pet Global',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clinic_id');

        $this->actingAs($user)
            ->postJson(route('sales.quick-customer.store'), [
                'clinic_id' => $clinic->id,
                'tutor_name' => 'Responsável Global',
                'tutor_phone' => '21999990001',
                'patient_name' => 'Pet Global',
            ])
            ->assertCreated()
            ->assertJsonPath('tutor.clinic_id', $clinic->id)
            ->assertJsonPath('patient.clinic_id', $clinic->id);
    }

    public function test_quick_pdv_preloads_the_selected_service_order_catalog(): void
    {
        $clinic = $this->clinic('PetShop Comanda PDV', '00000000001007');
        $service = PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Banho da comanda',
            'category' => 'Banho e Tosa',
            'base_price' => 65,
            'active' => true,
        ]);
        $order = ServiceOrder::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'CMD-PDV-001',
            'status' => 'waiting_pickup',
            'opened_at' => now(),
            'discount_total' => 5,
            'services_total' => 65,
            'total' => 60,
        ]);
        $order->items()->create([
            'type' => 'service',
            'petshop_service_id' => $service->id,
            'description' => 'Banho da comanda',
            'quantity' => 1,
            'unit_price' => 65,
            'total' => 65,
        ]);

        $response = $this->actingAs($this->salesUser($clinic))
            ->get(route('sales.create', ['service_order_id' => $order->id]));

        $response
            ->assertOk()
            ->assertSee('data-sale-service-order-catalog', false)
            ->assertSee('"description":"Banho da comanda"', false)
            ->assertSee('value="'.$order->id.'" data-clinic-id="'.$clinic->id.'" selected', false);
    }

    public function test_completed_sale_can_import_all_items_from_the_selected_service_order(): void
    {
        $clinic = $this->clinic('PetShop Fechamento Comanda', '00000000001008');
        $service = PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Tosa da comanda',
            'base_price' => 80,
            'active' => true,
        ]);
        $order = ServiceOrder::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'CMD-PDV-002',
            'status' => 'waiting_pickup',
            'opened_at' => now(),
            'services_total' => 80,
            'total' => 80,
        ]);
        $order->items()->create([
            'type' => 'service',
            'petshop_service_id' => $service->id,
            'description' => 'Tosa da comanda',
            'quantity' => 1,
            'unit_price' => 80,
            'total' => 80,
        ]);

        $user = $this->salesUser($clinic);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'service_order_id' => $order->id,
                'status' => 'completed',
                'source' => 'petshop_pdv',
                'payments' => [[
                    'method' => 'pix',
                    'amount' => 80,
                ]],
            ])
            ->assertRedirect(route('sales.index'))
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->with('items')->firstOrFail();

        $this->assertSame($order->id, $sale->service_order_id);
        $this->assertSame('completed', $sale->status);
        $this->assertEquals(80.0, (float) $sale->total);
        $this->assertSame('Tosa da comanda', $sale->items->first()->description);
        $this->assertSame('finished', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->closed_at);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'service_order_id' => $order->id,
                'status' => 'completed',
                'source' => 'petshop_pdv',
                'payments' => [[
                    'method' => 'cash',
                    'amount' => 80,
                ]],
            ])
            ->assertSessionHasErrors('service_order_id');

        $this->assertSame(1, Sale::query()->count());
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
    private function salesUser(?Clinic $clinic, array $permissionSlugs = ['sales.manage']): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);

        $role = Role::query()->create([
            'name' => 'Operador PDV '.Str::random(6),
            'slug' => 'operador-pdv-'.Str::lower(Str::random(8)),
            'description' => 'Role de teste do PDV.',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => 'Permissão '.Str::headline($permissionSlug),
                    'description' => 'Permissão de teste do PDV.',
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
