<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardOperationalInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_priorities_are_ordered_scoped_to_the_users_clinic_and_ignore_zero_values(): void
    {
        $clinic = $this->createClinic('Clinica A', '10000000000191');
        $otherClinic = $this->createClinic('Clinica B', '20000000000182');

        $this->createClinicSignals($clinic, 125.50, false);
        $this->createClinicSignals($otherClinic, 999.99, true);

        Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto inativo',
            'stock_quantity' => 0,
            'minimum_stock' => 5,
            'active' => false,
        ]);
        Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto sem minimo',
            'stock_quantity' => 0,
            'minimum_stock' => 0,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'active' => true,
        ]);
        $this->grantPermissions($user, [
            'dashboard.view',
            'appointments.manage',
            'schedules.manage',
            'service-orders.manage',
            'sales.manage',
            'financial.manage',
            'inventory.manage',
            'products.manage',
            'global-products.manage',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        $priorities = collect($response->viewData('operationalPriorities'));

        $this->assertSame([
            'expenses_overdue',
            'low_stock',
            'service_orders_waiting_pickup',
            'sales_pending_payment',
            'sales_drafts',
            'appointments_today',
        ], $priorities->pluck('key')->all());
        $this->assertSame(125.50, (float) $priorities->firstWhere('key', 'expenses_overdue')['value']);
        $this->assertSame(1.0, (float) $priorities->firstWhere('key', 'low_stock')['value']);
        $this->assertSame(40.0, (float) $priorities->firstWhere('key', 'sales_pending_payment')['value']);
        $this->assertFalse($priorities->contains('key', 'financial_overdue'));

        $alertStats = $response->viewData('alertSummary')['stats'];
        $this->assertSame(1, $alertStats['financial']);
        $this->assertSame($alertStats['inventory'] + $alertStats['financial'], $alertStats['total']);

        $response
            ->assertSee('data-dashboard-shell="control-center-v2"', false)
            ->assertSee('Prioridades operacionais')
            ->assertSee('data-dashboard-metric-link="financial-result"', false)
            ->assertSee('data-dashboard-metric-link="catalog-coverage"', false)
            ->assertSee('data-dashboard-priority="expenses_overdue"', false)
            ->assertSee('data-dashboard-priority="low_stock"', false)
            ->assertDontSee('data-dashboard-priority="financial_overdue"', false);
    }

    public function test_attendant_dashboard_does_not_receive_or_render_financial_inventory_or_product_intelligence_data(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $clinic = $this->createClinic('Clinica Atendente', '30000000000173');
        $this->createClinicSignals($clinic, 842.37, true);

        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Marina Souza Homologacao',
            'active' => true,
        ]);
        $this->attachRole($user, Role::query()->where('slug', 'atendimento')->firstOrFail());

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        $capabilities = $response->viewData('dashboardCapabilities');
        $this->assertTrue($capabilities['appointments']);
        $this->assertTrue($capabilities['serviceOrders']);
        $this->assertTrue($capabilities['sales']);
        $this->assertFalse($capabilities['financial']);
        $this->assertFalse($capabilities['inventory']);
        $this->assertFalse($capabilities['productIntelligence']);

        $stats = $response->viewData('stats');
        $this->assertArrayHasKey('sales_today', $stats);
        $this->assertArrayHasKey('service_orders_open', $stats);
        $this->assertArrayNotHasKey('financial_month', $stats);
        $this->assertArrayNotHasKey('expenses_overdue', $stats);
        $this->assertArrayNotHasKey('low_stock', $stats);
        $this->assertSame([], $response->viewData('alerts'));
        $this->assertSame([], $response->viewData('productIntelligence'));

        $priorities = collect($response->viewData('operationalPriorities'));
        $this->assertFalse($priorities->contains('key', 'expenses_overdue'));
        $this->assertFalse($priorities->contains('key', 'financial_overdue'));
        $this->assertFalse($priorities->contains('key', 'low_stock'));
        $this->assertTrue($priorities->contains('key', 'sales_pending_payment'));

        $response
            ->assertSee('Vendas hoje')
            ->assertSee('Abrir PDV')
            ->assertDontSee('Contas vencidas')
            ->assertDontSee('Pulso do mês')
            ->assertDontSee('Central de alertas')
            ->assertDontSee('Qualidade do catálogo')
            ->assertDontSee('data-dashboard-metric-link="financial-result"', false)
            ->assertDontSee('data-dashboard-metric-link="catalog-coverage"', false)
            ->assertDontSee('data-dashboard-priority="expenses_overdue"', false)
            ->assertDontSee('data-dashboard-priority="low_stock"', false);
    }

    private function createClinicSignals(Clinic $clinic, float $overdueExpense, bool $includeOverdueIncome): void
    {
        FinancialTransaction::query()->create([
            'clinic_id' => $clinic->id,
            'type' => 'expense',
            'description' => 'Conta vencida',
            'amount' => $overdueExpense,
            'due_date' => today()->subDay(),
            'status' => 'pending',
        ]);

        if ($includeOverdueIncome) {
            FinancialTransaction::query()->create([
                'clinic_id' => $clinic->id,
                'type' => 'income',
                'description' => 'Recebimento vencido',
                'amount' => 777.77,
                'due_date' => today()->subDay(),
                'status' => 'pending',
            ]);
        }

        Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto critico '.$clinic->id,
            'stock_quantity' => 1,
            'minimum_stock' => 5,
            'sale_price' => 10,
            'active' => true,
        ]);

        ServiceOrder::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'OS-'.$clinic->id,
            'status' => 'waiting_pickup',
            'opened_at' => now(),
        ]);

        Sale::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'DRAFT-'.$clinic->id,
            'status' => 'draft',
            'payment_status' => 'pending',
            'sold_at' => now(),
            'total' => 20,
        ]);
        Sale::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'PENDING-'.$clinic->id,
            'status' => 'completed',
            'payment_status' => 'partial',
            'sold_at' => now(),
            'total' => 40,
        ]);

        Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'title' => 'Consulta '.$clinic->id,
            'scheduled_at' => now()->startOfDay()->addHours(10),
            'status' => 'scheduled',
        ]);
    }

    private function createClinic(string $name, string $cnpj): Clinic
    {
        return Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function grantPermissions(User $user, array $permissionSlugs): void
    {
        $permissions = collect($permissionSlugs)->map(fn (string $permissionSlug): Permission => Permission::query()->create([
            'name' => 'Dashboard de teste '.$permissionSlug,
            'slug' => $permissionSlug,
            'description' => 'Permissao para teste do dashboard',
            'group' => 'Tests',
            'active' => true,
        ]));

        $role = Role::query()->create([
            'name' => 'Dashboard test role',
            'slug' => 'dashboard-test-role',
            'description' => 'Role para teste do dashboard',
            'system' => false,
            'active' => true,
        ]);

        $role->permissions()->attach($permissions->pluck('id'));

        $this->attachRole($user, $role);
    }

    private function attachRole(User $user, Role $role): void
    {
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
