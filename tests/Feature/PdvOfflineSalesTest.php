<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\OpensCashSessions;
use Tests\TestCase;

class PdvOfflineSalesTest extends TestCase
{
    use OpensCashSessions;
    use RefreshDatabase;

    public function test_offline_catalog_is_bounded_and_contains_no_customer_records(): void
    {
        $clinic = $this->clinic('Clínica Catálogo Offline', '00000000000901');
        $product = $this->product($clinic, 'Ração offline', 8, 42.50);
        $product->update(['sku' => 'OFF-01', 'barcode' => '7891000000001', 'gtin' => '7891000000001']);
        PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Banho offline',
            'category' => 'Banho e tosa',
            'base_price' => 65,
            'active' => true,
        ]);
        $user = $this->userFor($clinic);
        $this->openCashSession($user, $clinic, 100);

        $response = $this->actingAs($user)->getJson(route('sales.pdv.offline-catalog'));

        $response
            ->assertOk()
            ->assertJsonPath('schema', 1)
            ->assertJsonPath('complete', true)
            ->assertJsonStructure(['offline_context_token'])
            ->assertJsonPath('clinic.id', $clinic->id)
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.barcode', '7891000000001')
            ->assertJsonPath('items.1.type', 'service')
            ->assertJsonPath('cash_session.clinic_id', $clinic->id)
            ->assertJsonMissingPath('tutors')
            ->assertJsonMissingPath('patients');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_incomplete_catalog_is_explicit_instead_of_silently_enabling_offline_sales(): void
    {
        config()->set('sales.offline_catalog_limit', 1);
        $clinic = $this->clinic('Clínica Catálogo Limitado', '00000000000902');
        $this->product($clinic, 'Produto A', 2, 10);
        $this->product($clinic, 'Produto B', 2, 20);
        $user = $this->userFor($clinic);

        $this->actingAs($user)
            ->getJson(route('sales.pdv.offline-catalog'))
            ->assertOk()
            ->assertJsonPath('complete', false)
            ->assertJsonPath('total_items', 2)
            ->assertJsonCount(1, 'items');
    }

    public function test_offline_sale_sync_is_idempotent_and_applies_every_side_effect_once(): void
    {
        $clinic = $this->clinic('Clínica Sincronização Offline', '00000000000903');
        $product = $this->product($clinic, 'Antipulgas offline', 5, 30);
        $user = $this->userFor($clinic);
        $this->openCashSession($user, $clinic, 50);
        $requestId = (string) Str::uuid();
        $payload = $this->offlinePayload($clinic, $product, $user, $requestId);

        $first = $this->actingAs($user)->postJson(route('sales.pdv.offline-sync'), $payload);

        $first
            ->assertCreated()
            ->assertJsonPath('status', 'synced')
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('sale.total', 30);

        $second = $this->postJson(route('sales.pdv.offline-sync'), $payload);

        $second
            ->assertOk()
            ->assertJsonPath('status', 'synced')
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('sale.id', $first->json('sale.id'));

        $sale = Sale::query()->firstOrFail();
        $this->assertSame($requestId, $sale->client_request_id);
        $this->assertSame('pdv_offline', $sale->source);
        $this->assertNotNull($sale->offline_captured_at);
        $this->assertNotNull($sale->offline_synced_at);
        $this->assertEquals(4, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(1, InventoryMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertSame(1, FinancialTransaction::query()->whereKey($sale->financial_transaction_id)->count());
        $this->assertSame(1, $sale->events()->where('event_type', 'offline_synced')->count());
    }

    public function test_offline_request_id_cannot_be_replayed_by_another_operator(): void
    {
        $clinic = $this->clinic('Clínica Proteção Offline', '00000000000904');
        $product = $this->product($clinic, 'Produto protegido', 5, 15);
        $firstOperator = $this->userFor($clinic);
        $secondOperator = $this->userFor($clinic);
        $this->openCashSession($firstOperator, $clinic);
        $this->openCashSession($secondOperator, $clinic);
        $requestId = (string) Str::uuid();
        $payload = $this->offlinePayload($clinic, $product, $firstOperator, $requestId);

        $this->actingAs($firstOperator)
            ->postJson(route('sales.pdv.offline-sync'), $payload)
            ->assertCreated();

        $this->actingAs($secondOperator)
            ->postJson(route('sales.pdv.offline-sync'), $payload)
            ->assertConflict()
            ->assertJsonPath('status', 'conflict');

        $this->assertDatabaseCount('sales', 1);
        $this->assertEquals(4, (float) $product->fresh()->stock_quantity);
    }

    public function test_offline_sync_rejects_customer_credit_and_deferred_business_flows(): void
    {
        $clinic = $this->clinic('Clínica Restrições Offline', '00000000000905');
        $product = $this->product($clinic, 'Produto restrito', 5, 18);
        $user = $this->userFor($clinic);
        $this->openCashSession($user, $clinic);
        $payload = $this->offlinePayload($clinic, $product, $user, (string) Str::uuid());
        $payload['payments'] = [[
            'method' => 'customer_credit',
            'amount' => '18',
            'status' => 'paid',
        ]];

        $this->actingAs($user)
            ->postJson(route('sales.pdv.offline-sync'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payments');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_offline_stock_conflict_has_no_partial_side_effects(): void
    {
        $clinic = $this->clinic('Clínica Conflito Offline', '00000000000906');
        $product = $this->product($clinic, 'Última unidade', 1, 25);
        $user = $this->userFor($clinic);
        $this->openCashSession($user, $clinic);
        $payload = $this->offlinePayload($clinic, $product, $user, (string) Str::uuid());
        $payload['items'][0]['quantity'] = '2';
        $payload['payments'][0]['amount'] = '50';

        $this->actingAs($user)
            ->postJson(route('sales.pdv.offline-sync'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertEquals(1, (float) $product->fresh()->stock_quantity);
    }

    public function test_an_offline_queue_cannot_be_assumed_by_another_logged_operator(): void
    {
        $clinic = $this->clinic('Clínica Operador Offline', '00000000000907');
        $product = $this->product($clinic, 'Venda atribuída', 3, 22);
        $capturingOperator = $this->userFor($clinic);
        $otherOperator = $this->userFor($clinic);
        $this->openCashSession($capturingOperator, $clinic);
        $this->openCashSession($otherOperator, $clinic);
        $payload = $this->offlinePayload($clinic, $product, $capturingOperator, (string) Str::uuid());

        $this->actingAs($otherOperator)
            ->postJson(route('sales.pdv.offline-sync'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offline_context_token');

        $this->assertDatabaseCount('sales', 0);
        $this->assertEquals(3, (float) $product->fresh()->stock_quantity);
    }

    /** @return array<string, mixed> */
    private function offlinePayload(Clinic $clinic, Product $product, User $operator, string $requestId): array
    {
        $issuedAt = now()->subMinutes(2);

        return [
            'client_request_id' => $requestId,
            'offline_device_id' => (string) Str::uuid(),
            'offline_captured_at' => now()->subMinute()->toIso8601String(),
            'offline_context_token' => Crypt::encryptString(json_encode([
                'user_id' => $operator->id,
                'clinic_id' => $clinic->id,
                'issued_at' => $issuedAt->toIso8601String(),
                'expires_at' => $issuedAt->copy()->addDay()->toIso8601String(),
            ], JSON_THROW_ON_ERROR)),
            'clinic_id' => $clinic->id,
            'pdv_checkout' => true,
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => '1',
                'unit_price' => (string) $product->sale_price,
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => (string) $product->sale_price,
                'status' => 'paid',
            ]],
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

    private function product(Clinic $clinic, string $name, float $stock, float $price): Product
    {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cost_price' => $price / 2,
            'sale_price' => $price,
            'stock_quantity' => $stock,
            'minimum_stock' => 0,
            'active' => true,
        ]);
    }

    private function userFor(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Operador offline '.Str::random(6),
            'slug' => 'operador-offline-'.Str::lower(Str::random(8)),
            'description' => 'Perfil de teste do PDV offline',
            'system' => false,
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'sales.manage'],
            [
                'name' => 'Gerenciar vendas',
                'description' => 'Permissão de teste',
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
}
