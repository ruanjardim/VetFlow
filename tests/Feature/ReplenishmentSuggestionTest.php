<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\PurchaseEntries\Services\ReplenishmentSuggestionService;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReplenishmentSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestion_uses_recent_received_purchase_history_and_explains_the_result(): void
    {
        $this->travelTo('2026-08-01 10:00:00');

        $clinic = $this->clinic('Clinica Reposicao A', '00000000000601');
        $supplier = $this->supplier($clinic, 'Distribuidora Historica');
        $product = $this->product($clinic, 'Racao de reposicao', stock: 2, minimum: 5, cost: 9);
        $user = $this->userForClinic($clinic);

        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-HIST-001', 'received', 60, 10, 11);
        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-HIST-002', 'received', 20, 14, 13);
        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-DRAFT-001', 'draft', 10, 100, 50);
        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-OLD-001', 'received', 220, 80, 40);

        $this->actingAs($user);

        $suggestion = app(ReplenishmentSuggestionService::class)->suggestionFor($product);

        $this->assertSame(2, $suggestion['history_count']);
        $this->assertSame('medium', $suggestion['confidence']);
        $this->assertTrue($suggestion['uses_purchase_history']);
        $this->assertEquals(8.0, $suggestion['baseline_quantity']);
        $this->assertEquals(12.0, $suggestion['average_purchase_quantity']);
        $this->assertEquals(12.0, $suggestion['suggested_quantity']);
        $this->assertEquals(13.0, $suggestion['unit_cost']);
        $this->assertEquals(156.0, $suggestion['estimated_cost']);
        $this->assertSame(40, $suggestion['average_purchase_interval_days']);
        $this->assertSame($supplier->id, $suggestion['last_supplier_id']);
        $this->assertStringContainsString('lote médio de 2 compras', $suggestion['reason']);

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSee('Reposicao inteligente')
            ->assertSee('Racao de reposicao')
            ->assertSee('12,000')
            ->assertSee('Distribuidora Historica')
            ->assertSee('Confianca Media');

        $this->get($suggestion['purchase_url'])
            ->assertOk()
            ->assertViewHas('suggestedItem', function (?array $item) use ($product): bool {
                return $item !== null
                    && $item['product_id'] === $product->id
                    && (float) $item['quantity'] === 12.0
                    && (float) $item['unit_cost'] === 13.0
                    && $item['intelligence_status'] === 'replenishment_suggestion'
                    && $item['intelligence_metadata']['uses_purchase_history'] === true;
            });
    }

    public function test_suggestions_are_clinic_scoped_and_fall_back_to_the_minimum_stock_rule(): void
    {
        $clinicA = $this->clinic('Clinica Reposicao B', '00000000000611');
        $clinicB = $this->clinic('Clinica Reposicao C', '00000000000612');
        $localProduct = $this->product($clinicA, 'Produto local sem historico', stock: 1, minimum: 4, cost: 5);
        $externalProduct = $this->product($clinicB, 'Produto externo invisivel', stock: 0, minimum: 10, cost: 8);
        $user = $this->userForClinic($clinicA);

        $this->actingAs($user);

        $suggestions = app(ReplenishmentSuggestionService::class)->suggestions();

        $this->assertCount(1, $suggestions);
        $this->assertSame($localProduct->id, $suggestions->first()['product']->id);
        $this->assertNotSame($externalProduct->id, $suggestions->first()['product']->id);
        $this->assertSame(0, $suggestions->first()['history_count']);
        $this->assertSame('low', $suggestions->first()['confidence']);
        $this->assertFalse($suggestions->first()['uses_purchase_history']);
        $this->assertEquals(7.0, $suggestions->first()['suggested_quantity']);

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSee('Produto local sem historico')
            ->assertDontSee('Produto externo invisivel')
            ->assertSee('Sem compras recebidas no periodo');
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

    private function supplier(Clinic $clinic, string $name): Supplier
    {
        return Supplier::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function product(Clinic $clinic, string $name, float $stock, float $minimum, float $cost): Product
    {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cost_price' => $cost,
            'sale_price' => $cost * 2,
            'stock_quantity' => $stock,
            'minimum_stock' => $minimum,
            'unit' => 'un',
            'active' => true,
        ]);
    }

    private function purchaseBatch(
        Clinic $clinic,
        Supplier $supplier,
        Product $product,
        string $code,
        string $status,
        int $daysAgo,
        float $quantity,
        float $unitCost
    ): void {
        $receivedAt = $status === 'received' ? now()->subDays($daysAgo) : null;
        $entry = PurchaseEntry::query()->create([
            'clinic_id' => $clinic->id,
            'supplier_id' => $supplier->id,
            'code' => $code,
            'status' => $status,
            'purchased_at' => now()->subDays($daysAgo),
            'received_at' => $receivedAt,
            'subtotal' => $quantity * $unitCost,
            'total' => $quantity * $unitCost,
        ]);

        $entry->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
        ]);
    }

    private function userForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Reposicao '.Str::random(6),
            'slug' => 'reposicao-'.Str::lower(Str::random(8)),
            'description' => 'Teste de reposicao',
            'system' => false,
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'purchase-entries.manage'],
            [
                'name' => 'Gerenciar compras',
                'description' => 'Gerencia compras',
                'group' => 'Compras',
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
