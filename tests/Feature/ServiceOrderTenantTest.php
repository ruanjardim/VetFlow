<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceOrderTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_operator_must_select_an_active_clinic_for_the_service_order(): void
    {
        $clinic = $this->clinic('PetShop Comanda Global', '00000000002101');
        $inactiveClinic = $this->clinic('PetShop Comanda Inativa', '00000000002102', false);
        $user = $this->operator(null);

        $this->actingAs($user)
            ->get(route('service-orders.create'))
            ->assertOk()
            ->assertSee('name="clinic_id"', false)
            ->assertSee('PetShop Comanda Global')
            ->assertDontSee('PetShop Comanda Inativa');

        $this->post(route('service-orders.store'), $this->orderData())
            ->assertSessionHasErrors('clinic_id');

        $this->post(route('service-orders.store'), array_merge($this->orderData(), [
            'clinic_id' => $inactiveClinic->id,
        ]))->assertSessionHasErrors('clinic_id');

        $this->post(route('service-orders.store'), array_merge($this->orderData(), [
            'clinic_id' => $clinic->id,
        ]))
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($clinic->id, ServiceOrder::query()->firstOrFail()->clinic_id);
    }

    public function test_global_operator_cannot_mix_references_from_another_clinic(): void
    {
        $clinicA = $this->clinic('PetShop Comanda A', '00000000002103');
        $clinicB = $this->clinic('PetShop Comanda B', '00000000002104');
        $tutorA = $this->tutor($clinicA, 'Responsável A');
        $patientA = $this->patient($clinicA, $tutorA, 'Pet A');
        $productA = $this->product($clinicA, 'Shampoo A');
        $serviceA = $this->service($clinicA, 'Banho A');
        $tutorB = $this->tutor($clinicB, 'Responsável B');
        $patientB = $this->patient($clinicB, $tutorB, 'Pet B');
        $productB = $this->product($clinicB, 'Shampoo B');
        $serviceB = $this->service($clinicB, 'Banho B');
        $user = $this->operator(null);

        $this->actingAs($user)
            ->get(route('service-orders.create'))
            ->assertOk()
            ->assertSee('data-clinic-id="'.$clinicA->id.'"', false)
            ->assertSee('data-clinic-id="'.$clinicB->id.'"', false)
            ->assertSee('data-price-base="75.00"', false)
            ->assertSee('data-sale-price="25.00"', false);

        $this->post(route('service-orders.store'), [
            'clinic_id' => $clinicA->id,
            'tutor_id' => $tutorB->id,
            'patient_id' => $patientB->id,
            'status' => 'open',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $productB->id,
                    'quantity' => 1,
                ],
                [
                    'type' => 'service',
                    'petshop_service_id' => $serviceB->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertSessionHasErrors([
            'tutor_id',
            'patient_id',
            'items.0.product_id',
            'items.1.petshop_service_id',
        ]);

        $this->post(route('service-orders.store'), [
            'clinic_id' => $clinicA->id,
            'tutor_id' => $tutorA->id,
            'patient_id' => $patientA->id,
            'status' => 'open',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $productA->id,
                    'quantity' => 1,
                ],
                [
                    'type' => 'service',
                    'petshop_service_id' => $serviceA->id,
                    'quantity' => 1,
                ],
            ],
        ])
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionDoesntHaveErrors();

        $order = ServiceOrder::query()->with('items')->firstOrFail();
        $this->assertSame($clinicA->id, $order->clinic_id);
        $this->assertCount(2, $order->items);
    }

    public function test_service_order_rejects_a_pet_that_does_not_belong_to_the_selected_tutor(): void
    {
        $clinic = $this->clinic('PetShop Vinculo', '00000000002105');
        $tutorA = $this->tutor($clinic, 'Responsável correto');
        $tutorB = $this->tutor($clinic, 'Responsável incorreto');
        $patient = $this->patient($clinic, $tutorA, 'Pet vinculado');
        $user = $this->operator($clinic);

        $this->actingAs($user)
            ->post(route('service-orders.store'), array_merge($this->orderData(), [
                'tutor_id' => $tutorB->id,
                'patient_id' => $patient->id,
            ]))
            ->assertSessionHasErrors('patient_id');

        $this->assertDatabaseCount('service_orders', 0);
    }

    public function test_clinic_operator_cannot_override_the_service_order_clinic(): void
    {
        $clinic = $this->clinic('PetShop Operador Local', '00000000002106');
        $otherClinic = $this->clinic('PetShop Operador Externo', '00000000002107');
        $user = $this->operator($clinic);

        $this->actingAs($user)
            ->post(route('service-orders.store'), array_merge($this->orderData(), [
                'clinic_id' => $otherClinic->id,
            ]))
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('service_orders', [
            'clinic_id' => $clinic->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseMissing('service_orders', [
            'clinic_id' => $otherClinic->id,
        ]);
    }

    public function test_service_order_assigns_only_an_active_professional_from_the_same_clinic(): void
    {
        $clinic = $this->clinic('PetShop Equipe Local', '00000000002108');
        $otherClinic = $this->clinic('PetShop Equipe Externa', '00000000002109');
        $operator = $this->operator($clinic);
        $localProfessional = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Profissional Banho Local',
            'active' => true,
        ]);
        $inactiveProfessional = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Profissional Banho Inativo',
            'active' => false,
        ]);
        $externalProfessional = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Profissional Banho Externo',
            'active' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('service-orders.create'))
            ->assertOk()
            ->assertSee('Profissional Banho Local')
            ->assertDontSee('Profissional Banho Inativo')
            ->assertDontSee('Profissional Banho Externo');

        $this->post(route('service-orders.store'), array_merge($this->orderData(), [
            'assigned_user_id' => $externalProfessional->id,
        ]))->assertSessionHasErrors('assigned_user_id');

        $this->post(route('service-orders.store'), array_merge($this->orderData(), [
            'assigned_user_id' => $inactiveProfessional->id,
        ]))->assertSessionHasErrors('assigned_user_id');

        $this->post(route('service-orders.store'), array_merge($this->orderData(), [
            'assigned_user_id' => $localProfessional->id,
        ]))
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($localProfessional->id, ServiceOrder::query()->firstOrFail()->assigned_user_id);
    }

    private function clinic(string $name, string $cnpj, bool $active = true): Clinic
    {
        return Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => $active,
        ]);
    }

    private function operator(?Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Operação Comanda '.Str::random(6),
            'slug' => 'operacao-comanda-'.Str::lower(Str::random(8)),
            'system' => false,
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'service-orders.manage'],
            [
                'name' => 'Gerenciar comandas',
                'group' => 'Tests',
                'active' => true,
            ]
        );
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

    private function tutor(Clinic $clinic, string $name): Tutor
    {
        return Tutor::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'phone' => '21999990000',
            'active' => true,
        ]);
    }

    private function patient(Clinic $clinic, Tutor $tutor, string $name): Patient
    {
        return Patient::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => $name,
            'species' => 'Canino',
        ]);
    }

    private function product(Clinic $clinic, string $name): Product
    {
        return Product::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'sale_price' => 25,
            'active' => true,
        ]);
    }

    private function service(Clinic $clinic, string $name): PetShopService
    {
        return PetShopService::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'base_price' => 75,
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function orderData(): array
    {
        return [
            'status' => 'open',
            'opened_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
