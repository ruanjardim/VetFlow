<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Support\PatientSize;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroomingAgendaTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $operator;

    private User $groomer;

    private User $otherGroomer;

    private Tutor $tutor;

    private Patient $largeDog;

    private PetShopService $bath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-23 07:00:00'));

        $this->clinic = $this->clinic('PetShop Agenda', '00000000003001');
        $this->operator = $this->userForClinic($this->clinic, [
            'service-orders.manage',
            'sales.manage',
            'schedules.manage',
        ]);
        $this->groomer = User::factory()->create(['name' => 'Adriana Tosadora', 'active' => true, 'clinic_id' => $this->clinic->id]);
        $this->otherGroomer = User::factory()->create(['name' => 'João Banhista', 'active' => true, 'clinic_id' => $this->clinic->id]);

        $this->tutor = Tutor::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rackel Martins',
            'phone' => '21976800110',
            'active' => true,
        ]);
        $this->largeDog = Patient::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id,
            'tutor_id' => $this->tutor->id,
            'name' => 'Thor',
            'weight' => 32,
        ]);
        $this->bath = PetShopService::query()->withoutGlobalScopes()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Banho',
            'base_price' => 60,
            'small_price' => 55,
            'medium_price' => 80,
            'large_price' => 100,
            'giant_price' => 150,
            'duration_minutes' => 90,
            'requires_appointment' => true,
            'active' => true,
        ]);
    }

    public function test_size_is_suggested_from_weight_and_explicit_size_wins(): void
    {
        $this->assertSame('small', PatientSize::suggestFromWeight(4));
        $this->assertSame('medium', PatientSize::suggestFromWeight(25));
        $this->assertSame('large', PatientSize::suggestFromWeight(32));
        $this->assertSame('giant', PatientSize::suggestFromWeight(60));
        $this->assertNull(PatientSize::suggestFromWeight(null));
        $this->assertSame('small', PatientSize::resolve('small', 60));
        $this->assertSame('large_price', PatientSize::priceColumn('large'));
    }

    public function test_booking_uses_the_pet_size_price_and_the_service_duration(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertRedirect(route('service-orders.index'))
            ->assertSessionHasNoErrors();

        $order = ServiceOrder::query()->sole();

        $this->assertSame('scheduled', $order->status);
        $this->assertSame($this->groomer->id, $order->assigned_user_id);
        $this->assertSame(90, $order->duration_minutes);
        $this->assertSame('100.00', (string) $order->total);
        $this->assertNull($order->checked_in_at);
    }

    public function test_double_booking_the_same_professional_is_blocked_unless_marked_as_fit_in(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 11:00'))
            ->assertSessionHasErrors('scheduled_at');

        $this->assertSame(1, ServiceOrder::query()->count());

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 11:00', [
                'assigned_user_id' => $this->otherGroomer->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 11:00', [
                'allow_overlap' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, ServiceOrder::query()->count());
    }

    public function test_cancelled_or_no_show_bookings_free_the_slot(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $first = ServiceOrder::query()->sole();

        $this->actingAs($this->operator)
            ->patch(route('service-orders.status', $first->id), ['status' => 'no_show'])
            ->assertSessionHasNoErrors();

        $this->assertSame('no_show', $first->refresh()->status);
        $this->assertNotNull($first->closed_at);

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:30'))
            ->assertSessionHasNoErrors();
    }

    public function test_recurring_booking_creates_every_occurrence_with_the_same_services(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 09:00', [
                'recurrence_frequency' => 'weekly',
                'recurrence_count' => 4,
            ]))
            ->assertSessionHasNoErrors();

        $orders = ServiceOrder::query()->with('items')->orderBy('scheduled_at')->get();

        $this->assertCount(4, $orders);
        $this->assertSame(
            ['2026-09-23 09:00', '2026-09-30 09:00', '2026-10-07 09:00', '2026-10-14 09:00'],
            $orders->map(fn (ServiceOrder $order) => $order->scheduled_at->format('Y-m-d H:i'))->all()
        );
        $this->assertCount(1, $orders->pluck('recurrence_group')->unique());
        $this->assertNotNull($orders->first()->recurrence_group);
        $orders->each(function (ServiceOrder $order): void {
            $this->assertSame('scheduled', $order->status);
            $this->assertSame('100.00', (string) $order->total);
            $this->assertCount(1, $order->items);
        });
        $this->assertCount(4, $orders->pluck('code')->unique());
    }

    public function test_recurring_booking_reports_conflicting_dates(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-30 09:30'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 09:00', [
                'recurrence_frequency' => 'weekly',
                'recurrence_count' => 3,
            ]))
            ->assertSessionHasErrors(['scheduled_at' => 'Horário indisponível para o profissional: 30/09 às 09:00 já está ocupado com Thor (CMD-000001). Escolha outro horário ou marque "Encaixe".']);

        $this->assertSame(1, ServiceOrder::query()->count());
    }

    public function test_editing_a_booking_does_not_conflict_with_itself(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $order = ServiceOrder::query()->sole();

        $this->actingAs($this->operator)
            ->put(route('service-orders.update', $order->id), $this->bookingPayload('2026-09-23 10:30', [
                'notes' => 'Tosa higiênica',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-23 10:30', $order->refresh()->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame(1, ServiceOrder::query()->count());
    }

    public function test_check_in_flow_records_operational_timestamps(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $order = ServiceOrder::query()->sole();

        foreach (['confirmed', 'open', 'in_service', 'waiting_pickup'] as $status) {
            $this->actingAs($this->operator)
                ->patch(route('service-orders.status', $order->id), ['status' => $status])
                ->assertSessionHasNoErrors();
        }

        $order->refresh();
        $this->assertSame('waiting_pickup', $order->status);
        $this->assertNotNull($order->checked_in_at);
        $this->assertNotNull($order->started_at);
        $this->assertNotNull($order->ready_at);
        $this->assertNull($order->closed_at);
    }

    public function test_availability_lists_only_free_slots_for_the_professional(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $slots = $this->actingAs($this->operator)
            ->getJson(route('service-orders.availability', [
                'date' => '2026-09-23',
                'assigned_user_id' => $this->groomer->id,
                'duration_minutes' => 60,
            ]))
            ->assertOk()
            ->json('slots');

        $this->assertContains('08:00', $slots);
        $this->assertContains('09:00', $slots);
        $this->assertNotContains('09:30', $slots);
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('11:00', $slots);
        $this->assertContains('11:30', $slots);
        $this->assertContains('17:00', $slots);
        $this->assertNotContains('17:30', $slots);

        $freeForOther = $this->actingAs($this->operator)
            ->getJson(route('service-orders.availability', [
                'date' => '2026-09-23',
                'assigned_user_id' => $this->otherGroomer->id,
                'duration_minutes' => 60,
            ]))
            ->json('slots');

        $this->assertContains('10:00', $freeForOther);
    }

    public function test_agenda_shows_a_column_per_professional_without_leaking_other_clinics(): void
    {
        // Fixtures externos antes de autenticar: o tenant do usuario logado carimba o clinic_id.
        $otherClinic = $this->clinic('PetShop Vizinho', '00000000003002');
        $foreignTutor = Tutor::query()->withoutGlobalScopes()->create([
            'clinic_id' => $otherClinic->id, 'name' => 'Tutor Externo', 'phone' => '21900000000', 'active' => true,
        ]);
        $foreignPet = Patient::query()->withoutGlobalScopes()->create([
            'clinic_id' => $otherClinic->id, 'tutor_id' => $foreignTutor->id, 'name' => 'Pet Externo',
        ]);
        ServiceOrder::query()->withoutGlobalScopes()->create([
            'clinic_id' => $otherClinic->id,
            'tutor_id' => $foreignTutor->id,
            'patient_id' => $foreignPet->id,
            'code' => 'CMD-EXTERNA',
            'status' => 'scheduled',
            'opened_at' => now(),
            'scheduled_at' => '2026-09-23 10:00:00',
        ]);

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->get(route('service-orders.agenda', ['date' => '2026-09-23']))
            ->assertOk()
            ->assertSee('Agenda banho e tosa')
            ->assertSee('Adriana Tosadora')
            ->assertSee('João Banhista')
            ->assertSee('Thor')
            ->assertSee('10:00–11:30')
            ->assertSee('Chegou')
            ->assertDontSee('CMD-EXTERNA')
            ->assertDontSee('Tutor Externo');
    }

    public function test_agenda_columns_follow_the_grooming_professional_flag(): void
    {
        $columns = fn (): array => $this->agendaColumns(
            $this->actingAs($this->operator)
                ->get(route('service-orders.agenda', ['date' => '2026-09-23']))
                ->assertOk()
                ->getContent()
        );

        $this->assertSame(['Adriana Tosadora', 'João Banhista', 'Recepção'], $columns());

        $this->groomer->update(['grooming_professional' => true]);

        $this->assertSame(['Adriana Tosadora'], $columns());

        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00', [
                'assigned_user_id' => $this->otherGroomer->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Adriana Tosadora', 'João Banhista'], $columns());
    }

    /** @return array<int, string> */
    private function agendaColumns(string $html): array
    {
        preg_match_all('/class="grooming-agenda-head">\s*<strong>([^<]+)<\/strong>/u', $html, $matches);

        return array_map(fn (string $name) => html_entity_decode(trim($name)), $matches[1]);
    }

    public function test_board_lists_todays_bookings_in_the_scheduled_column(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-24 10:00'))
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->operator)
            ->get(route('service-orders.board', ['date' => '2026-09-23']))
            ->assertOk()
            ->assertSee('Agendados')
            ->assertSee('Chegou (check-in)');

        $this->assertSame(1, substr_count($response->getContent(), 'Pet não informado') + substr_count($response->getContent(), '<strong>Thor</strong>'));
    }

    public function test_pdv_opens_prefilled_with_the_ready_order(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $order = ServiceOrder::query()->sole();
        $order->update(['status' => 'waiting_pickup']);

        $this->actingAs($this->operator)
            ->get(route('sales.create', ['service_order_id' => $order->id]))
            ->assertOk()
            ->assertSee('Recebendo a comanda')
            ->assertSee($order->code)
            ->assertSee('name="service_order_id" value="'.$order->id.'"', false)
            ->assertSee('Banho', false);
    }

    public function test_visual_agenda_can_filter_grooming_bookings(): void
    {
        $this->actingAs($this->operator)
            ->post(route('service-orders.store'), $this->bookingPayload('2026-09-23 10:00'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->get(route('schedules.index', ['date' => '2026-09-23', 'view' => 'day', 'area' => 'grooming']))
            ->assertOk()
            ->assertSee('Banho e tosa · Adriana Tosadora · Agendado')
            ->assertSee('Thor');

        $this->actingAs($this->operator)
            ->get(route('schedules.index', ['date' => '2026-09-23', 'view' => 'day', 'area' => 'clinic']))
            ->assertOk()
            ->assertDontSee('Adriana Tosadora · Agendado');
    }

    /** @param array<string, mixed> $overrides */
    private function bookingPayload(string $scheduledAt, array $overrides = []): array
    {
        return array_merge([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'tutor_id' => $this->tutor->id,
            'patient_id' => $this->largeDog->id,
            'assigned_user_id' => $this->groomer->id,
            'discount_total' => 0,
            'items' => [
                ['type' => 'service', 'petshop_service_id' => $this->bath->id, 'quantity' => 1],
            ],
        ], $overrides);
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

    /** @param array<int, string> $permissions */
    private function userForClinic(Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create([
            'name' => 'Recepção',
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Recepção PetShop '.Str::random(6),
            'slug' => 'recepcao-petshop-'.Str::lower(Str::random(8)),
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
