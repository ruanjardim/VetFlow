<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Sales\Models\Sale;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceOrderBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_groups_the_clinics_operational_orders_without_leaking_another_tenant(): void
    {
        $clinic = $this->clinic('PetShop Quadro', '00000000002001');
        $otherClinic = $this->clinic('PetShop Externo', '00000000002002');
        $localOrder = $this->order($clinic, 'CMD-LOCAL', 'in_service');
        $externalOrder = $this->order($otherClinic, 'CMD-EXTERNA', 'waiting_pickup');

        $response = $this->actingAs($this->userForClinic($clinic))
            ->get(route('service-orders.board', ['date' => now()->toDateString()]));

        $response
            ->assertOk()
            ->assertSee('Operação Banho e Tosa')
            ->assertSee($localOrder->code)
            ->assertDontSee($externalOrder->code)
            ->assertSee('Em atendimento')
            ->assertSee('Aguardando retirada');
    }

    public function test_status_endpoint_closes_and_reopens_an_order_consistently(): void
    {
        $clinic = $this->clinic('PetShop Etapas', '00000000002003');
        $order = $this->order($clinic, 'CMD-ETAPAS', 'waiting_pickup');
        $user = $this->userForClinic($clinic);

        $this->actingAs($user)
            ->patch(route('service-orders.status', $order->id), ['status' => 'finished'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('finished', $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertNotNull($order->ready_at);
        $this->assertNotNull($order->closed_at);

        $this->actingAs($user)
            ->patch(route('service-orders.status', $order->id), ['status' => 'in_service'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('in_service', $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertNull($order->ready_at);
        $this->assertNull($order->closed_at);
    }

    public function test_status_endpoint_cannot_update_an_order_from_another_clinic(): void
    {
        $clinic = $this->clinic('PetShop Local', '00000000002004');
        $otherClinic = $this->clinic('PetShop Fora', '00000000002005');
        $externalOrder = $this->order($otherClinic, 'CMD-FORA', 'open');

        $this->actingAs($this->userForClinic($clinic))
            ->patch(route('service-orders.status', $externalOrder->id), ['status' => 'in_service'])
            ->assertNotFound();

        $this->assertSame('open', $externalOrder->fresh()->status);
    }

    public function test_order_history_cannot_be_reopened_or_deleted_after_a_completed_sale(): void
    {
        $clinic = $this->clinic('PetShop Histórico', '00000000002006');
        $order = $this->order($clinic, 'CMD-HISTORICO', 'finished');
        $user = $this->userForClinic($clinic);

        Sale::query()->create([
            'clinic_id' => $clinic->id,
            'service_order_id' => $order->id,
            'code' => 'VEN-HISTORICO',
            'status' => 'completed',
            'payment_status' => 'paid',
            'sold_at' => now(),
            'total' => 75,
            'paid_total' => 75,
        ]);

        $this->actingAs($user)
            ->patch(route('service-orders.status', $order->id), ['status' => 'in_service'])
            ->assertSessionHasErrors('status');

        $this->actingAs($user)
            ->delete(route('service-orders.destroy', $order->id))
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertSame('finished', $order->status);
        $this->assertNull($order->deleted_at);
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

    private function order(Clinic $clinic, string $code, string $status): ServiceOrder
    {
        $tutor = Tutor::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Responsável '.$code,
            'phone' => '21999990000',
            'active' => true,
        ]);
        $patient = Patient::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'name' => 'Pet '.$code,
        ]);

        return ServiceOrder::query()->withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'tutor_id' => $tutor->id,
            'patient_id' => $patient->id,
            'code' => $code,
            'status' => $status,
            'opened_at' => now()->subHour(),
            'scheduled_at' => now(),
            'total' => 75,
        ]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'service-orders.manage'],
            [
                'name' => 'Gerenciar comandas',
                'description' => 'Permissão de teste das comandas.',
                'group' => 'Tests',
                'active' => true,
            ]
        );
        $role = Role::query()->create([
            'name' => 'Operação PetShop '.Str::random(6),
            'slug' => 'operacao-petshop-'.Str::lower(Str::random(8)),
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
