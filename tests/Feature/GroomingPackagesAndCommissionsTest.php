<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Commissions\Models\GroomingCommission;
use App\Modules\Commissions\Models\GroomingCommissionSettlement;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetPackage;
use App\Modules\PetShopServices\Models\PetshopPackage;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Sales\Models\Sale;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroomingPackagesAndCommissionsTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $manager;

    private User $groomer;

    private Tutor $tutor;

    private Patient $dog;

    private PetShopService $bath;

    private PetShopService $haircut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-23 07:00:00'));

        $this->clinic = Clinic::query()->create([
            'corporate_name' => 'PetShop Pacotes',
            'trade_name' => 'PetShop Pacotes',
            'cnpj' => '00000000004001',
            'active' => true,
        ]);
        $this->manager = $this->userWith([
            'service-orders.manage',
            'petshop-services.manage',
            'sales.manage',
            'commissions.manage',
        ], 'Gerente');
        $this->groomer = User::factory()->create([
            'name' => 'Adriana Tosadora',
            'active' => true,
            'clinic_id' => $this->clinic->id,
            'grooming_professional' => true,
            'grooming_commission_percent' => 30,
        ]);

        $this->tutor = Tutor::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id, 'name' => 'Rackel Martins', 'phone' => '21976800110', 'active' => true,
        ]);
        $this->dog = Patient::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id, 'tutor_id' => $this->tutor->id, 'name' => 'Thor', 'weight' => 32,
        ]);
        $this->bath = PetShopService::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id, 'name' => 'Banho', 'base_price' => 60, 'large_price' => 100,
            'duration_minutes' => 60, 'commission_percent' => 40, 'active' => true, 'requires_appointment' => true,
        ]);
        $this->haircut = PetShopService::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id, 'name' => 'Tosa', 'base_price' => 80,
            'duration_minutes' => 60, 'active' => true, 'requires_appointment' => true,
        ]);
    }

    public function test_package_template_is_saved_with_its_services(): void
    {
        $this->actingAs($this->manager)
            ->post(route('petshop-packages.store'), [
                'name' => 'Clubinho G',
                'price' => 320,
                'validity_days' => 30,
                'active' => 1,
                'items' => [
                    ['petshop_service_id' => $this->bath->id, 'quantity' => 4],
                    ['petshop_service_id' => '', 'quantity' => ''],
                ],
            ])
            ->assertRedirect(route('petshop-packages.index'))
            ->assertSessionHasNoErrors();

        $template = PetshopPackage::query()->with('items')->sole();
        $this->assertSame('Clubinho G', $template->name);
        $this->assertSame(4, $template->totalSessions());

        $this->actingAs($this->manager)
            ->get(route('petshop-packages.index'))
            ->assertOk()
            ->assertSee('Clubinho G')
            ->assertSee('4× Banho');
    }

    public function test_selling_a_package_goes_to_the_pdv_and_payment_activates_it(): void
    {
        $template = $this->template();

        $response = $this->actingAs($this->manager)
            ->post(route('pet-packages.store'), [
                'petshop_package_id' => $template->id,
                'patient_id' => $this->dog->id,
                'starts_on' => '2026-09-23',
            ]);

        $package = PetPackage::query()->with('balances')->sole();
        $response->assertRedirect(route('sales.create', ['pet_package_id' => $package->id]));

        $this->assertSame('pending_payment', $package->status);
        $this->assertSame('2026-10-22', $package->expires_on->toDateString());
        $this->assertSame(4, $package->totalQuantity());
        $this->assertSame('50.00', (string) $package->balances->first()->unit_value);

        $this->actingAs($this->manager)
            ->get(route('sales.create', ['pet_package_id' => $package->id]))
            ->assertOk()
            ->assertSee('Vendendo o pacote')
            ->assertSee('name="pet_package_id" value="'.$package->id.'"', false);

        $this->actingAs($this->manager)
            ->post(route('sales.store'), [
                'status' => 'completed',
                'source' => 'pdv',
                'tutor_id' => $this->tutor->id,
                'patient_id' => $this->dog->id,
                'pet_package_id' => $package->id,
                'items' => [['type' => 'custom', 'description' => 'Pacote Clubinho', 'quantity' => 1, 'unit_price' => 200]],
                'payments' => [['method' => 'pix', 'amount' => 200]],
            ])
            ->assertSessionHasNoErrors();

        $package->refresh();
        $this->assertSame('active', $package->status);
        $this->assertNotNull($package->sale_id);

        $this->actingAs($this->manager)
            ->patch(route('sales.cancel', $package->sale_id), ['reason' => 'Desistência'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $package->refresh()->status);
    }

    public function test_booking_consumes_the_package_balance_and_cancelling_releases_it(): void
    {
        $package = $this->activePackage();

        $this->book('2026-09-24 10:00');
        $order = ServiceOrder::query()->with('items')->sole();

        $this->assertSame('0.00', (string) $order->total);
        $this->assertSame('0.00', (string) $order->items->first()->unit_price);
        $this->assertStringContainsString('pacote '.$package->code, $order->items->first()->description);
        $this->assertSame(3, $package->refresh()->load(['balances', 'usages'])->remainingTotal());

        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $package->refresh()->load(['balances', 'usages'])->remainingTotal());
    }

    public function test_opting_out_of_the_package_charges_the_size_price_again(): void
    {
        $package = $this->activePackage();
        $this->book('2026-09-24 10:00');
        $order = ServiceOrder::query()->with('items')->sole();
        $item = $order->items->first();

        $this->actingAs($this->manager)
            ->put(route('service-orders.update', $order->id), [
                'status' => 'scheduled',
                'scheduled_at' => '2026-09-24 10:00',
                'tutor_id' => $this->tutor->id,
                'patient_id' => $this->dog->id,
                'assigned_user_id' => $this->groomer->id,
                'use_package_balance' => '0',
                'items' => [[
                    'type' => 'service',
                    'petshop_service_id' => $this->bath->id,
                    'description' => $item->description,
                    'quantity' => 1,
                    'unit_price' => '0.00',
                    'from_package' => 1,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $order->refresh()->load('items');
        $this->assertSame('100.00', (string) $order->total);
        $this->assertSame('Banho', $order->items->first()->description);
        $this->assertSame(4, $package->refresh()->load(['balances', 'usages'])->remainingTotal());
    }

    public function test_expired_or_unpaid_packages_are_not_used(): void
    {
        $package = $this->activePackage();
        $this->book('2026-11-30 10:00');

        $this->assertSame('100.00', (string) ServiceOrder::query()->sole()->total);

        $package->update(['status' => 'pending_payment']);
        $this->book('2026-09-25 10:00');

        $this->assertSame('100.00', (string) ServiceOrder::query()->latest('id')->first()->total);
    }

    public function test_finishing_an_order_creates_the_professional_commission(): void
    {
        $this->book('2026-09-23 10:00', [
            'discount_total' => 36,
            'items' => [
                ['type' => 'service', 'petshop_service_id' => $this->bath->id, 'quantity' => 1],
                ['type' => 'service', 'petshop_service_id' => $this->haircut->id, 'quantity' => 1],
            ],
        ]);
        $order = ServiceOrder::query()->sole();

        $this->assertSame(0, GroomingCommission::query()->count());

        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'finished'])
            ->assertSessionHasNoErrors();

        $entries = GroomingCommission::query()->orderBy('id')->get();
        $this->assertCount(2, $entries);

        // Banho R$100 e Tosa R$80 = R$180; desconto R$36 rateado (20%).
        $this->assertSame('80.00', (string) $entries[0]->base_amount);
        $this->assertSame('40.00', (string) $entries[0]->percentage);
        $this->assertSame('32.00', (string) $entries[0]->amount);
        $this->assertSame('64.00', (string) $entries[1]->base_amount);
        $this->assertSame('30.00', (string) $entries[1]->percentage);
        $this->assertSame('19.20', (string) $entries[1]->amount);

        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'finished'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, GroomingCommission::query()->count());
    }

    public function test_package_services_pay_commission_on_the_session_value(): void
    {
        $this->activePackage();
        $this->book('2026-09-23 10:00');
        $order = ServiceOrder::query()->sole();

        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'finished'])
            ->assertSessionHasNoErrors();

        $entry = GroomingCommission::query()->sole();
        $this->assertSame('50.00', (string) $entry->base_amount);
        $this->assertSame('20.00', (string) $entry->amount);
    }

    public function test_reopening_cancels_pending_commissions_and_reverses_settled_ones(): void
    {
        $this->book('2026-09-23 10:00');
        $order = ServiceOrder::query()->sole();
        $this->finish($order);

        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'waiting_pickup'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', GroomingCommission::query()->sole()->status);

        $this->finish($order);
        $this->actingAs($this->manager)
            ->post(route('grooming-commissions.settle'), [
                'user_id' => $this->groomer->id,
                'until' => '2026-09-30',
                'due_date' => '2026-10-05',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'waiting_pickup'])
            ->assertSessionHasNoErrors();

        $reversal = GroomingCommission::query()->where('kind', 'reversal')->sole();
        $this->assertSame('-40.00', (string) $reversal->amount);
        $this->assertSame('pending', $reversal->status);
    }

    public function test_settlement_creates_a_payable_and_closes_the_entries(): void
    {
        $this->book('2026-09-23 10:00');
        $this->finish(ServiceOrder::query()->sole());

        $this->actingAs($this->manager)
            ->get(route('grooming-commissions.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Adriana Tosadora')
            ->assertSee('Fechar e gerar conta');

        $this->actingAs($this->manager)
            ->post(route('grooming-commissions.settle'), [
                'user_id' => $this->groomer->id,
                'until' => '2026-09-30',
                'due_date' => '2026-10-05',
            ])
            ->assertSessionHasNoErrors();

        $settlement = GroomingCommissionSettlement::query()->sole();
        $this->assertSame('40.00', (string) $settlement->total);
        $this->assertSame('settled', GroomingCommission::query()->sole()->status);

        $payable = FinancialTransaction::query()->findOrFail($settlement->financial_transaction_id);
        $this->assertSame('expense', $payable->type);
        $this->assertSame('pending', $payable->status);
        $this->assertSame('40.00', (string) $payable->amount);
        $this->assertStringContainsString('Adriana Tosadora', $payable->description);

        $this->actingAs($this->manager)
            ->post(route('grooming-commissions.settle'), [
                'user_id' => $this->groomer->id,
                'until' => '2026-09-30',
                'due_date' => '2026-10-05',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_sale_from_the_board_generates_commission_and_cancellation_reverts_it(): void
    {
        $this->book('2026-09-23 10:00');
        $order = ServiceOrder::query()->sole();
        $order->update(['status' => 'waiting_pickup']);

        $this->actingAs($this->manager)
            ->post(route('sales.store'), [
                'status' => 'completed',
                'source' => 'pdv',
                'service_order_id' => $order->id,
                'tutor_id' => $this->tutor->id,
                'patient_id' => $this->dog->id,
                'items' => [['type' => 'service', 'petshop_service_id' => $this->bath->id, 'description' => 'Banho', 'quantity' => 1, 'unit_price' => 100]],
                'payments' => [['method' => 'pix', 'amount' => 100]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('finished', $order->refresh()->status);
        $this->assertSame('pending', GroomingCommission::query()->sole()->status);

        $this->actingAs($this->manager)
            ->patch(route('sales.cancel', Sale::query()->sole()->id), ['reason' => 'Erro'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', GroomingCommission::query()->sole()->status);
    }

    public function test_releasing_a_package_without_pdv_requires_cashier_permission(): void
    {
        $package = app(\App\Modules\PetShopServices\Services\PetPackageService::class)->sell([
            'petshop_package_id' => $this->template()->id,
            'patient_id' => $this->dog->id,
            'starts_on' => '2026-09-23',
        ]);
        $attendant = $this->userWith(['service-orders.manage'], 'Atendente');

        $this->actingAs($attendant)
            ->patch(route('pet-packages.activate', $package->id))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->patch(route('pet-packages.activate', $package->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $package->refresh()->status);

        $this->actingAs($this->manager)
            ->get(route('pet-packages.index'))
            ->assertOk()
            ->assertSee($package->code)
            ->assertSee('4 de 4');
    }

    private function template(): PetshopPackage
    {
        auth()->login($this->manager);
        $template = app(\App\Modules\PetShopServices\Services\PetPackageService::class)->saveTemplate([
            'name' => 'Clubinho G',
            'price' => 200,
            'validity_days' => 30,
            'items' => [['petshop_service_id' => $this->bath->id, 'quantity' => 4]],
        ]);
        auth()->logout();

        return $template;
    }

    private function activePackage(): PetPackage
    {
        $template = $this->template();
        auth()->login($this->manager);
        $package = app(\App\Modules\PetShopServices\Services\PetPackageService::class)->sell([
            'petshop_package_id' => $template->id,
            'patient_id' => $this->dog->id,
            'starts_on' => '2026-09-23',
            'activate_now' => true,
        ]);
        auth()->logout();

        return $package->refresh();
    }

    /** @param array<string, mixed> $overrides */
    private function book(string $scheduledAt, array $overrides = []): void
    {
        $this->actingAs($this->manager)
            ->post(route('service-orders.store'), array_merge([
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'tutor_id' => $this->tutor->id,
                'patient_id' => $this->dog->id,
                'assigned_user_id' => $this->groomer->id,
                'allow_overlap' => 1,
                'discount_total' => 0,
                'items' => [['type' => 'service', 'petshop_service_id' => $this->bath->id, 'quantity' => 1]],
            ], $overrides))
            ->assertSessionHasNoErrors();
    }

    private function finish(ServiceOrder $order): void
    {
        $this->actingAs($this->manager)
            ->patch(route('service-orders.status', $order->id), ['status' => 'finished'])
            ->assertSessionHasNoErrors();
    }

    /** @param array<int, string> $permissions */
    private function userWith(array $permissions, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'active' => true, 'clinic_id' => $this->clinic->id]);
        $role = Role::query()->create([
            'name' => $name.' '.Str::random(6),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'description' => 'Permissão de teste.', 'group' => 'Tests', 'active' => true]
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
