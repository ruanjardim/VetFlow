<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\PurchaseEntries\Models\ReplenishmentReviewEvent;
use App\Modules\PurchaseEntries\Services\ReplenishmentEvidenceService;
use App\Modules\PurchaseEntries\Services\ReplenishmentSuggestionService;
use App\Modules\Sales\Models\Sale;
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

        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-HIST-001', 'received', 60, 10, 11, 6);
        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-HIST-002', 'received', 20, 14, 13, 10);
        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-DRAFT-001', 'draft', 10, 100, 50);
        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-OLD-001', 'received', 220, 80, 40);
        $this->sale($clinic, $product, 'VEN-DEM-001', 'completed', 10, 6, 1);
        $this->sale($clinic, $product, 'VEN-DEM-002', 'returned', 30, 4, 2);
        $this->sale($clinic, $product, 'VEN-DRAFT-001', 'draft', 5, 100, 0);
        $this->sale($clinic, $product, 'VEN-OLD-001', 'completed', 100, 50, 0);

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
        $this->assertTrue($suggestion['has_recent_demand']);
        $this->assertSame(2, $suggestion['demand_sales_count']);
        $this->assertEquals(10.0, $suggestion['demand_sold_quantity']);
        $this->assertEquals(3.0, $suggestion['demand_returned_quantity']);
        $this->assertEquals(7.0, $suggestion['net_demand_quantity']);
        $this->assertEquals(2.333, $suggestion['average_monthly_demand']);
        $this->assertStringContainsString('demanda líquida recente foi de 7,000', $suggestion['reason']);
        $this->assertTrue($suggestion['has_reference_supplier_history']);
        $this->assertTrue($suggestion['has_supplier_lead_time']);
        $this->assertSame(2, $suggestion['reference_supplier_deliveries']);
        $this->assertEquals(24.0, $suggestion['reference_supplier_quantity_received']);
        $this->assertEquals(12.0, $suggestion['reference_supplier_average_batch_quantity']);
        $this->assertEquals(12.17, $suggestion['reference_supplier_average_unit_cost']);
        $this->assertSame(2, $suggestion['reference_supplier_lead_time_samples']);
        $this->assertSame(8, $suggestion['reference_supplier_average_lead_time_days']);
        $this->assertSame(6, $suggestion['reference_supplier_minimum_lead_time_days']);
        $this->assertSame(10, $suggestion['reference_supplier_maximum_lead_time_days']);
        $this->assertStringContainsString('prazo médio observado de Distribuidora Historica foi de 8 dia(s)', $suggestion['reason']);
        $this->assertSame('covered', $suggestion['coverage_risk']);
        $this->assertSame('Cobertura acima do prazo', $suggestion['coverage_risk_label']);
        $this->assertEquals(0.0778, $suggestion['daily_demand_quantity']);
        $this->assertEquals(25.7, $suggestion['coverage_days']);
        $this->assertEquals(17.7, $suggestion['coverage_margin_days']);
        $this->assertEquals(1.378, $suggestion['projected_stock_at_receipt']);
        $this->assertStringContainsString('cobertura estimada de 25,7 dia(s) supera', mb_strtolower($suggestion['reason']));

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSee('Reposicao inteligente')
            ->assertSee('Racao de reposicao')
            ->assertSee('12,000')
            ->assertSee('Distribuidora Historica')
            ->assertSee('Demanda recente')
            ->assertSee('7,000')
            ->assertSee('Devolucoes descontadas: 3,000')
            ->assertSee('Prazo medio observado: 8 dias')
            ->assertSee('Faixa: 6 a 10 dias')
            ->assertSee('Cobertura acima do prazo')
            ->assertSee('Cobertura estimada: 25,7 dias')
            ->assertSee('Margem observada: 17,7 dias')
            ->assertSee('Confianca Media');

        $this->get($suggestion['purchase_url'])
            ->assertOk()
            ->assertViewHas('suggestedItem', function (?array $item) use ($product): bool {
                return $item !== null
                    && $item['product_id'] === $product->id
                    && (float) $item['quantity'] === 12.0
                    && (float) $item['unit_cost'] === 13.0
                    && $item['intelligence_status'] === 'replenishment_suggestion'
                    && $item['intelligence_metadata']['uses_purchase_history'] === true
                    && (float) $item['intelligence_metadata']['net_demand_quantity'] === 7.0
                    && $item['intelligence_metadata']['reference_supplier_average_lead_time_days'] === 8
                    && $item['intelligence_metadata']['coverage_risk'] === 'covered'
                    && strlen($item['intelligence_metadata']['evidence']['hash']) === 64
                    && strlen($item['intelligence_metadata']['evidence']['signature']) === 64
                    && app(ReplenishmentEvidenceService::class)->validEnvelope(
                        $item['intelligence_metadata']['evidence']
                    );
            });
    }

    public function test_replenishment_evidence_rejects_changes_to_the_signed_snapshot(): void
    {
        $clinic = $this->clinic('Clinica Evidencia Assinada', '00000000000605');
        $product = $this->product($clinic, 'Produto com evidencia assinada', stock: 2, minimum: 5, cost: 9);
        $user = $this->userForClinic($clinic);

        $this->actingAs($user);

        $suggestion = app(ReplenishmentSuggestionService::class)->suggestionFor($product);
        $evidence = app(ReplenishmentEvidenceService::class);
        $envelope = $evidence->envelope($suggestion);

        $this->assertTrue($evidence->validEnvelope($envelope));
        $this->assertSame($clinic->id, $envelope['snapshot']['clinic_id']);
        $this->assertSame($product->id, $envelope['snapshot']['product_id']);

        $envelope['snapshot']['suggested_quantity'] = 999;

        $this->assertFalse($evidence->validEnvelope($envelope));
    }

    public function test_suggestions_are_clinic_scoped_and_fall_back_to_the_minimum_stock_rule(): void
    {
        $clinicA = $this->clinic('Clinica Reposicao B', '00000000000611');
        $clinicB = $this->clinic('Clinica Reposicao C', '00000000000612');
        $localProduct = $this->product($clinicA, 'Produto local sem historico', stock: 1, minimum: 4, cost: 5);
        $externalProduct = $this->product($clinicB, 'Produto externo invisivel', stock: 0, minimum: 10, cost: 8);
        $user = $this->userForClinic($clinicA);
        $this->sale($clinicB, $externalProduct, 'VEN-EXTERNA-001', 'completed', 2, 80, 0);

        $this->actingAs($user);

        $suggestions = app(ReplenishmentSuggestionService::class)->suggestions();

        $this->assertCount(1, $suggestions);
        $this->assertSame($localProduct->id, $suggestions->first()['product']->id);
        $this->assertNotSame($externalProduct->id, $suggestions->first()['product']->id);
        $this->assertSame(0, $suggestions->first()['history_count']);
        $this->assertSame('low', $suggestions->first()['confidence']);
        $this->assertFalse($suggestions->first()['uses_purchase_history']);
        $this->assertFalse($suggestions->first()['has_recent_demand']);
        $this->assertEquals(0.0, $suggestions->first()['net_demand_quantity']);
        $this->assertFalse($suggestions->first()['has_reference_supplier_history']);
        $this->assertFalse($suggestions->first()['has_supplier_lead_time']);
        $this->assertSame('insufficient', $suggestions->first()['coverage_risk']);
        $this->assertNull($suggestions->first()['coverage_days']);
        $this->assertEquals(7.0, $suggestions->first()['suggested_quantity']);

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSee('Produto local sem historico')
            ->assertDontSee('Produto externo invisivel')
            ->assertSee('Sem compras recebidas no periodo')
            ->assertSee('Sem demanda liquida no periodo')
            ->assertSee('Base insuficiente');
    }

    public function test_coverage_flags_when_stock_may_end_before_observed_receipt(): void
    {
        $this->travelTo('2026-08-01 10:00:00');

        $clinic = $this->clinic('Clinica Risco de Ruptura', '00000000000621');
        $supplier = $this->supplier($clinic, 'Fornecedor Prazo Observado');
        $product = $this->product($clinic, 'Produto com cobertura curta', stock: 2, minimum: 5, cost: 10);
        $user = $this->userForClinic($clinic);

        $this->purchaseBatch($clinic, $supplier, $product, 'ENT-RISCO-001', 'received', 5, 10, 10, 7);
        $this->sale($clinic, $product, 'VEN-RISCO-001', 'completed', 2, 90, 0);

        $this->actingAs($user);

        $suggestion = app(ReplenishmentSuggestionService::class)->suggestionFor($product);

        $this->assertSame('risk', $suggestion['coverage_risk']);
        $this->assertSame('Risco de ruptura', $suggestion['coverage_risk_label']);
        $this->assertEquals(1.0, $suggestion['daily_demand_quantity']);
        $this->assertEquals(2.0, $suggestion['coverage_days']);
        $this->assertSame(7, $suggestion['coverage_lead_time_days']);
        $this->assertEquals(-5.0, $suggestion['coverage_margin_days']);
        $this->assertEquals(0.0, $suggestion['projected_stock_at_receipt']);
        $this->assertStringContainsString('menor ou igual ao prazo médio observado', $suggestion['reason']);

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSee('Produto com cobertura curta')
            ->assertSee('Risco de ruptura')
            ->assertSee('Cobertura estimada: 2,0 dias')
            ->assertSee('Deficit estimado: 5,0 dias')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['coverage_risk'] === 1);
    }

    public function test_human_reviews_are_validated_and_appended_to_the_history(): void
    {
        $clinic = $this->clinic('Clinica Revisao Humana', '00000000000631');
        $product = $this->product($clinic, 'Produto revisado manualmente', stock: 2, minimum: 5, cost: 10);
        $user = $this->userForClinic($clinic);

        $this->actingAs($user);

        $this->post(route('purchase-entries.replenishment-reviews.store', $product), [
            'decision' => 'held',
            'note' => '',
        ])->assertSessionHasErrors('note');

        $this->assertDatabaseCount('replenishment_review_events', 0);

        $this->post(route('purchase-entries.replenishment-reviews.store', $product), [
            'decision' => 'reviewed',
            'note' => 'Quantidade e custo conferidos.',
        ])->assertRedirect(route('purchase-entries.replenishment').'#replenishment-product-'.$product->id);

        $this->post(route('purchase-entries.replenishment-reviews.store', $product), [
            'decision' => 'held',
            'note' => 'Aguardar confirmacao do fornecedor.',
        ])->assertRedirect(route('purchase-entries.replenishment').'#replenishment-product-'.$product->id);

        $events = ReplenishmentReviewEvent::query()->orderBy('id')->get();

        $this->assertCount(2, $events);
        $this->assertSame('reviewed', $events[0]->decision);
        $this->assertSame('held', $events[1]->decision);
        $this->assertSame($events[0]->evidence_hash, $events[1]->evidence_hash);
        $this->assertSame('Quantidade e custo conferidos.', $events[0]->note);
        $this->assertSame('Aguardar confirmacao do fornecedor.', $events[1]->note);

        $this->get(route('purchase-entries.replenishment-reviews'))
            ->assertOk()
            ->assertSeeText('Produto revisado manualmente')
            ->assertSeeText('Revisada')
            ->assertSeeText('Em espera')
            ->assertSeeText('Quantidade e custo conferidos.')
            ->assertSeeText('Aguardar confirmacao do fornecedor.');
    }

    public function test_review_becomes_stale_when_the_underlying_evidence_changes(): void
    {
        $clinic = $this->clinic('Clinica Evidencia de Revisao', '00000000000641');
        $product = $this->product($clinic, 'Produto com evidencia mutavel', stock: 1, minimum: 5, cost: 10);
        $user = $this->userForClinic($clinic);

        $this->actingAs($user);

        $this->post(route('purchase-entries.replenishment-reviews.store', $product), [
            'decision' => 'reviewed',
        ])->assertSessionHasNoErrors();

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSeeText('Revisada')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['reviews_current'] === 1
                && $stats['reviews_stale'] === 0
                && $stats['reviews_pending'] === 0);

        $product->update(['stock_quantity' => 3]);

        $this->get(route('purchase-entries.replenishment'))
            ->assertOk()
            ->assertSeeText('Revisão superada')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['reviews_current'] === 0
                && $stats['reviews_stale'] === 1
                && $stats['reviews_pending'] === 0);

        $this->get(route('purchase-entries.replenishment-reviews'))
            ->assertOk()
            ->assertSeeText('Evidencia superada');
    }

    public function test_review_history_and_product_actions_are_isolated_by_clinic(): void
    {
        $clinicA = $this->clinic('Clinica Revisao Isolada A', '00000000000651');
        $clinicB = $this->clinic('Clinica Revisao Isolada B', '00000000000652');
        $productA = $this->product($clinicA, 'Produto local da revisao', stock: 1, minimum: 4, cost: 5);
        $productB = $this->product($clinicB, 'Produto externo da revisao', stock: 1, minimum: 4, cost: 5);
        $userA = $this->userForClinic($clinicA);
        $userB = $this->userForClinic($clinicB);

        $this->actingAs($userB)
            ->post(route('purchase-entries.replenishment-reviews.store', $productB), [
                'decision' => 'reviewed',
                'note' => 'Registro exclusivo da clinica B.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($userA);

        $this->get(route('purchase-entries.replenishment-reviews'))
            ->assertOk()
            ->assertDontSeeText('Produto externo da revisao')
            ->assertDontSeeText('Registro exclusivo da clinica B.');

        $this->post(route('purchase-entries.replenishment-reviews.store', $productB), [
            'decision' => 'reviewed',
        ])->assertNotFound();

        $this->post(route('purchase-entries.replenishment-reviews.store', $productA), [
            'decision' => 'reviewed',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('replenishment_review_events', 2);
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
        float $unitCost,
        int $leadTimeDays = 0,
    ): void {
        $receivedAt = $status === 'received' ? now()->subDays($daysAgo) : null;
        $entry = PurchaseEntry::query()->create([
            'clinic_id' => $clinic->id,
            'supplier_id' => $supplier->id,
            'code' => $code,
            'status' => $status,
            'purchased_at' => now()->subDays($daysAgo + $leadTimeDays),
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

    private function sale(
        Clinic $clinic,
        Product $product,
        string $code,
        string $status,
        int $daysAgo,
        float $quantity,
        float $returnedQuantity,
    ): void {
        $completedAt = $status === 'draft' ? null : now()->subDays($daysAgo);
        $sale = Sale::query()->create([
            'clinic_id' => $clinic->id,
            'code' => $code,
            'status' => $status,
            'payment_status' => $status === 'draft' ? 'pending' : 'paid',
            'sold_at' => now()->subDays($daysAgo),
            'completed_at' => $completedAt,
            'subtotal' => $quantity * 20,
            'total' => $quantity * 20,
            'stock_applied' => $status !== 'draft',
            'financial_applied' => $status !== 'draft',
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'type' => 'product',
            'description' => $product->name,
            'quantity' => $quantity,
            'returned_quantity' => $returnedQuantity,
            'unit_price' => 20,
            'total' => $quantity * 20,
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
