<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\Schedules\Models\Schedule;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseAndClinicalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_purchase_entry_applies_inventory_and_payable_for_current_clinic(): void
    {
        $clinic = $this->clinic('Clinica Compras A', '00000000000301');
        $supplier = $this->supplier($clinic, 'Distribuidora local');
        $product = $this->product($clinic, 'Racao terapeutica', stock: 3, costPrice: 10, salePrice: 18);
        $user = $this->userForClinic($clinic, ['purchase-entries.manage']);

        $response = $this->actingAs($user)->post(route('purchase-entries.store'), [
            'supplier_id' => $supplier->id,
            'status' => 'received',
            'invoice_number' => 'NF-100',
            'payment_due_date' => today()->addDays(10)->toDateString(),
            'payment_status' => 'pending',
            'payment_method' => 'bank_slip',
            'installments_count' => 2,
            'installment_interval_days' => 15,
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Racao terapeutica',
                    'quantity' => '5',
                    'unit_cost' => '12.50',
                    'sale_price' => '24.90',
                    'update_sale_price' => true,
                    'minimum_stock_after_entry' => '4',
                    'lot_number' => 'LT-01',
                    'expires_at' => today()->addYear()->toDateString(),
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('purchase-entries.index'))
            ->assertSessionDoesntHaveErrors();

        $entry = PurchaseEntry::query()->with(['items', 'financialTransactions'])->firstOrFail();
        $movement = InventoryMovement::query()->where('purchase_entry_id', $entry->id)->firstOrFail();
        $payables = FinancialTransaction::query()->where('purchase_entry_id', $entry->id)->orderBy('installment_number')->get();

        $this->assertSame($clinic->id, (int) $entry->clinic_id);
        $this->assertSame($supplier->id, (int) $entry->supplier_id);
        $this->assertSame('received', $entry->status);
        $this->assertEquals(62.50, (float) $entry->total);
        $this->assertCount(1, $entry->items);

        $this->assertSame($clinic->id, (int) $movement->clinic_id);
        $this->assertSame('entry', $movement->type);
        $this->assertSame('purchase_entry', $movement->source);
        $this->assertEquals(3.0, (float) $movement->balance_before);
        $this->assertEquals(8.0, (float) $movement->balance_after);

        $product->refresh();
        $this->assertEquals(8.0, (float) $product->stock_quantity);
        $this->assertEquals(12.50, (float) $product->cost_price);
        $this->assertEquals(24.90, (float) $product->sale_price);
        $this->assertEquals(4.0, (float) $product->minimum_stock);

        $this->assertCount(2, $payables);
        $this->assertTrue($payables->every(fn (FinancialTransaction $transaction) => (int) $transaction->clinic_id === $clinic->id));
        $this->assertSame([31.25, 31.25], $payables->map(fn ($transaction) => (float) $transaction->amount)->all());
    }

    public function test_purchase_entry_rejects_supplier_and_product_from_another_clinic(): void
    {
        $clinicA = $this->clinic('Clinica Compras B', '00000000000311');
        $clinicB = $this->clinic('Clinica Compras C', '00000000000312');
        $otherSupplier = $this->supplier($clinicB, 'Fornecedor externo');
        $otherProduct = $this->product($clinicB, 'Produto externo compra', stock: 2, costPrice: 9);
        $user = $this->userForClinic($clinicA, ['purchase-entries.manage']);

        $response = $this
            ->actingAs($user)
            ->from(route('purchase-entries.create'))
            ->post(route('purchase-entries.store'), [
                'supplier_id' => $otherSupplier->id,
                'status' => 'received',
                'items' => [
                    [
                        'product_id' => $otherProduct->id,
                        'quantity' => '1',
                        'unit_cost' => '9',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('purchase-entries.create'))
            ->assertSessionHasErrors(['supplier_id', 'items.0.product_id']);

        $this->assertDatabaseCount('purchase_entries', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertEquals(
            2.0,
            (float) DB::table('products')->where('id', $otherProduct->id)->value('stock_quantity')
        );
    }

    public function test_service_order_accepts_current_clinic_items_and_rejects_external_references(): void
    {
        $clinicA = $this->clinic('Clinica Comanda A', '00000000000321');
        $clinicB = $this->clinic('Clinica Comanda B', '00000000000322');
        $tutor = $this->tutor($clinicA, 'Tutor Local');
        $patient = $this->patient($clinicA, 'Paciente Local');
        $product = $this->product($clinicA, 'Shampoo medicamentoso', salePrice: 40);
        $service = $this->petShopService($clinicA, 'Banho terapeutico', 80);
        $externalTutor = $this->tutor($clinicB, 'Tutor Externo');
        $externalProduct = $this->product($clinicB, 'Produto externo comanda', salePrice: 50);
        $externalService = $this->petShopService($clinicB, 'Servico externo', 70);
        $user = $this->userForClinic($clinicA, ['service-orders.manage']);

        $response = $this->actingAs($user)->post(route('service-orders.store'), [
            'tutor_id' => $tutor->id,
            'patient_id' => $patient->id,
            'status' => 'open',
            'discount_total' => '10',
            'items' => [
                [
                    'type' => 'service',
                    'petshop_service_id' => $service->id,
                    'quantity' => '1',
                ],
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'quantity' => '2',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionDoesntHaveErrors();

        $order = ServiceOrder::query()->with('items')->firstOrFail();

        $this->assertSame($clinicA->id, (int) $order->clinic_id);
        $this->assertSame($tutor->id, (int) $order->tutor_id);
        $this->assertSame($patient->id, (int) $order->patient_id);
        $this->assertCount(2, $order->items);
        $this->assertEquals(80.0, (float) $order->services_total);
        $this->assertEquals(80.0, (float) $order->products_total);
        $this->assertEquals(150.0, (float) $order->total);

        $response = $this
            ->from(route('service-orders.create'))
            ->post(route('service-orders.store'), [
                'tutor_id' => $externalTutor->id,
                'status' => 'open',
                'items' => [
                    [
                        'type' => 'product',
                        'product_id' => $externalProduct->id,
                        'quantity' => '1',
                    ],
                    [
                        'type' => 'service',
                        'petshop_service_id' => $externalService->id,
                        'quantity' => '1',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('service-orders.create'))
            ->assertSessionHasErrors(['tutor_id', 'items.0.product_id', 'items.1.petshop_service_id']);

        $this->assertSame(1, ServiceOrder::query()->count());
    }

    public function test_appointments_and_schedules_reject_patients_and_tutors_from_another_clinic(): void
    {
        $clinicA = $this->clinic('Clinica Agenda A', '00000000000331');
        $clinicB = $this->clinic('Clinica Agenda B', '00000000000332');
        $externalTutor = $this->tutor($clinicB, 'Tutor agenda externo');
        $externalPatient = $this->patient($clinicB, 'Paciente agenda externo');
        $user = $this->userForClinic($clinicA, ['appointments.manage', 'schedules.manage']);

        $appointmentResponse = $this
            ->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), [
                'tutor_id' => $externalTutor->id,
                'patient_id' => $externalPatient->id,
                'title' => 'Consulta externa indevida',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
            ]);

        $appointmentResponse
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors(['tutor_id', 'patient_id']);

        $scheduleResponse = $this
            ->from(route('schedules.create'))
            ->post(route('schedules.store'), [
                'tutor_id' => $externalTutor->id,
                'patient_id' => $externalPatient->id,
                'title' => 'Agenda externa indevida',
                'scheduled_date' => today()->addDay()->toDateString(),
                'scheduled_time' => '09:30',
                'status' => 'agendado',
            ]);

        $scheduleResponse
            ->assertRedirect(route('schedules.create'))
            ->assertSessionHasErrors(['tutor_id', 'patient_id']);

        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(0, Schedule::query()->count());
    }

    public function test_appointment_and_schedule_screens_use_clinic_scoped_selects_and_names(): void
    {
        $clinicA = $this->clinic('Clinica Agenda C', '00000000000341');
        $clinicB = $this->clinic('Clinica Agenda D', '00000000000342');
        $localTutor = $this->tutor($clinicA, 'Tutor Agenda Local');
        $localPatient = $this->patient($clinicA, 'Paciente Agenda Local');
        $externalTutor = $this->tutor($clinicB, 'Tutor Agenda Externo');
        $externalPatient = $this->patient($clinicB, 'Paciente Agenda Externo');
        $user = $this->userForClinic($clinicA, ['appointments.manage', 'schedules.manage']);

        $this->actingAs($user)
            ->get(route('appointments.create'))
            ->assertOk()
            ->assertSee('Paciente Agenda Local')
            ->assertSee('Tutor Agenda Local')
            ->assertDontSee('Paciente Agenda Externo')
            ->assertDontSee('Tutor Agenda Externo')
            ->assertDontSee('Paciente ID')
            ->assertDontSee('Tutor ID');

        $this->get(route('schedules.create'))
            ->assertOk()
            ->assertSee('Paciente Agenda Local')
            ->assertSee('Tutor Agenda Local')
            ->assertDontSee('Paciente Agenda Externo')
            ->assertDontSee('Tutor Agenda Externo')
            ->assertDontSee('Paciente ID')
            ->assertDontSee('Tutor ID');

        Appointment::query()->create([
            'clinic_id' => $clinicA->id,
            'patient_id' => $localPatient->id,
            'tutor_id' => $localTutor->id,
            'title' => 'Consulta local listada',
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        Schedule::query()->create([
            'clinic_id' => $clinicA->id,
            'patient_id' => $localPatient->id,
            'tutor_id' => $localTutor->id,
            'title' => 'Agenda local listada',
            'scheduled_date' => today()->addDay(),
            'scheduled_time' => '10:00',
            'status' => 'agendado',
        ]);

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Consulta local listada')
            ->assertSee('Paciente Agenda Local')
            ->assertSee('Tutor Agenda Local')
            ->assertDontSee($externalPatient->name)
            ->assertDontSee($externalTutor->name);

        $this->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Agenda local listada')
            ->assertSee('Paciente Agenda Local')
            ->assertSee('Tutor Agenda Local')
            ->assertDontSee($externalPatient->name)
            ->assertDontSee($externalTutor->name);
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

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);

        $this->grantPermissions($user, $permissionSlugs);

        return $user;
    }

    private function supplier(Clinic $clinic, string $name): Supplier
    {
        return Supplier::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function product(
        Clinic $clinic,
        string $name,
        float $stock = 0,
        float $costPrice = 0,
        float $salePrice = 0
    ): Product {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'stock_quantity' => $stock,
            'active' => true,
        ]);
    }

    private function petShopService(Clinic $clinic, string $name, float $basePrice): PetShopService
    {
        return PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'base_price' => $basePrice,
            'active' => true,
        ]);
    }

    private function tutor(Clinic $clinic, string $name): Tutor
    {
        return Tutor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'phone' => '2199999'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'active' => true,
        ]);
    }

    private function patient(Clinic $clinic, string $name): Patient
    {
        return Patient::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'species' => 'canino',
        ]);
    }

    private function grantPermissions(User $user, array $permissionSlugs): void
    {
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
    }
}
