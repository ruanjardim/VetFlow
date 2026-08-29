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
use App\Modules\PurchaseEntries\Services\ReplenishmentPurchaseHistoryService;
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

    public function test_saved_purchase_measures_operator_changes_against_valid_evidence(): void
    {
        $this->travelTo('2026-08-01 10:00:00');

        $clinic = $this->clinic('Clinica Decisao de Compra', '00000000000607');
        $suggestedSupplier = $this->supplier($clinic, 'Fornecedor sugerido');
        $selectedSupplier = $this->supplier($clinic, 'Fornecedor escolhido');
        $product = $this->product($clinic, 'Produto com decisao medida', stock: 2, minimum: 5, cost: 9);
        $user = $this->userForClinic($clinic);

        $this->purchaseBatch($clinic, $suggestedSupplier, $product, 'ENT-MED-001', 'received', 60, 10, 11, 6);
        $this->purchaseBatch($clinic, $suggestedSupplier, $product, 'ENT-MED-002', 'received', 20, 14, 13, 10);

        $this->actingAs($user);

        $suggestion = app(ReplenishmentSuggestionService::class)->suggestionFor($product);
        $prefillResponse = $this->get($suggestion['purchase_url'])
            ->assertOk()
            ->assertSeeText('Justificativa das alterações da reposição')
            ->assertSeeText('Preço, prazo ou condição comercial')
            ->assertSee('replenishment-adjustment-reason-0');
        $prefill = $prefillResponse->viewData('suggestedItem');

        $adjustedPurchase = [
            'supplier_id' => $selectedSupplier->id,
            'status' => 'draft',
            'purchased_at' => now()->format('Y-m-d'),
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 15,
                'unit_cost' => 14,
                'sale_price' => 20,
                'intelligence_status' => $prefill['intelligence_status'],
                'intelligence_metadata' => json_encode($prefill['intelligence_metadata'], JSON_THROW_ON_ERROR),
            ]],
        ];

        $this->post(route('purchase-entries.store'), $adjustedPurchase)
            ->assertSessionHasErrors('items.0.replenishment_adjustment_reason');
        $this->assertSame(2, PurchaseEntry::query()->count());

        $adjustedPurchase['items'][0]['replenishment_adjustment_reason'] = 'other';

        $this->post(route('purchase-entries.store'), $adjustedPurchase)
            ->assertSessionHasErrors('items.0.replenishment_adjustment_note');
        $this->assertSame(2, PurchaseEntry::query()->count());

        $adjustedPurchase['items'][0]['replenishment_adjustment_reason'] = 'commercial_terms';
        $adjustedPurchase['items'][0]['replenishment_adjustment_note'] = 'Condição negociada diretamente com o fornecedor.';

        $this->post(route('purchase-entries.store'), $adjustedPurchase)
            ->assertRedirect(route('purchase-entries.index'));

        $entry = PurchaseEntry::query()->latest('id')->firstOrFail();
        $decision = $entry->items()->sole()->intelligence_metadata['replenishment_decision'];

        $this->assertSame('valid', $decision['evidence_status']);
        $this->assertSame(2, $decision['version']);
        $this->assertSame('adjusted', $decision['classification']);
        $this->assertEquals(12.0, $decision['quantity']['suggested']);
        $this->assertEquals(15.0, $decision['quantity']['actual']);
        $this->assertEquals(3.0, $decision['quantity']['delta']);
        $this->assertEquals(25.0, $decision['quantity']['delta_percent']);
        $this->assertTrue($decision['quantity']['changed']);
        $this->assertEquals(13.0, $decision['unit_cost']['suggested']);
        $this->assertEquals(14.0, $decision['unit_cost']['actual']);
        $this->assertEquals(1.0, $decision['unit_cost']['delta']);
        $this->assertEquals(7.69, $decision['unit_cost']['delta_percent']);
        $this->assertTrue($decision['unit_cost']['changed']);
        $this->assertSame($suggestedSupplier->id, $decision['supplier']['suggested_id']);
        $this->assertSame($selectedSupplier->id, $decision['supplier']['actual_id']);
        $this->assertSame('changed', $decision['supplier']['status']);
        $this->assertSame('commercial_terms', $decision['adjustment_reason']['code']);
        $this->assertSame('Preço, prazo ou condição comercial', $decision['adjustment_reason']['label']);
        $this->assertSame(
            'Condição negociada diretamente com o fornecedor.',
            $decision['adjustment_reason']['note'],
        );
        $this->assertSame(
            $prefill['intelligence_metadata']['evidence']['hash'],
            $decision['evidence_hash'],
        );

        $this->post(route('purchase-entries.store'), [
            'supplier_id' => $suggestedSupplier->id,
            'status' => 'draft',
            'purchased_at' => now()->format('Y-m-d'),
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $prefill['quantity'],
                'unit_cost' => $prefill['unit_cost'],
                'sale_price' => 20,
                'intelligence_status' => $prefill['intelligence_status'],
                'intelligence_metadata' => json_encode($prefill['intelligence_metadata'], JSON_THROW_ON_ERROR),
            ]],
        ])->assertRedirect(route('purchase-entries.index'));

        $keptEntry = PurchaseEntry::query()->latest('id')->firstOrFail();
        $kept = $keptEntry->items()->sole()->intelligence_metadata['replenishment_decision'];

        $this->assertSame('kept', $kept['classification']);
        $this->assertFalse($kept['quantity']['changed']);
        $this->assertFalse($kept['unit_cost']['changed']);
        $this->assertSame('kept', $kept['supplier']['status']);
        $this->assertNull($kept['adjustment_reason']);

        $this->get(route('purchase-entries.edit', $entry))
            ->assertOk()
            ->assertSeeText('Condição negociada diretamente com o fornecedor.');

        $this->get(route('purchase-entries.replenishment-purchases'))
            ->assertOk()
            ->assertSeeText('Decisões de compra da reposição')
            ->assertSeeText($entry->code)
            ->assertSeeText($keptEntry->code)
            ->assertSeeText('Produto com decisao medida')
            ->assertSeeText('Ajustada')
            ->assertSeeText('Mantida')
            ->assertSeeText('Fornecedor escolhido')
            ->assertSeeText('Fornecedor sugerido')
            ->assertSeeText('Motivo do ajuste')
            ->assertSeeText('Preço, prazo ou condição comercial')
            ->assertSeeText('Condição negociada diretamente com o fornecedor.')
            ->assertSeeText('+3,000')
            ->assertSeeText('+25,00%')
            ->assertSeeText('+R$ 1,00')
            ->assertSeeText('Adesão às sugestões')
            ->assertSeeText('50,0%')
            ->assertSeeText('Quantidade alterada: 1')
            ->assertSeeText('Custo alterado: 1')
            ->assertSeeText('Fornecedor alterado: 1')
            ->assertSeeText('Últimos 90 dias, pela data da compra')
            ->assertSeeText('Maturidade da amostra do piloto')
            ->assertSeeText('Baixar relatório JSON')
            ->assertSeeText('Amostra em formação')
            ->assertSeeText('2 de 4 critérios atendidos')
            ->assertSeeText('Registre mais 18 decisão(ões) comparável(is)')
            ->assertSeeText('não comprova significância estatística')
            ->assertSeeText('Divergências por produto')
            ->assertSeeText('1 produto(s) analisado(s)')
            ->assertSeeText('1 mantida(s)')
            ->assertSeeText('1 ajustada(s)')
            ->assertSeeText('Ajustes: 50,0%')
            ->assertSeeText('Qtd: 1')
            ->assertSeeText('Custo: 1')
            ->assertSeeText('Fornecedor: 1')
            ->assertSeeText('quantidade 12,50%;')
            ->assertSeeText('custo 3,85%.')
            ->assertDontSeeText($prefill['intelligence_metadata']['evidence']['signature'])
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total'] === 2
                && $stats['comparable'] === 2
                && $stats['kept'] === 1
                && $stats['adjusted'] === 1
                && $stats['unavailable'] === 0
                && $stats['adherence_percent'] === 50.0
                && $stats['quantity_adjusted'] === 1
                && $stats['unit_cost_adjusted'] === 1
                && $stats['supplier_adjusted'] === 1
                && $stats['adjustments_with_reason'] === 1
                && $stats['evidence_coverage_percent'] === 100.0
                && $stats['adjustment_reason_coverage_percent'] === 100.0
                && $stats['average_abs_quantity_delta_percent'] === 12.5
                && $stats['average_abs_unit_cost_delta_percent'] === 3.85
                && $stats['product_count'] === 1
                && $stats['comparable_product_count'] === 1
                && $stats['maturity']['status'] === 'building'
                && $stats['maturity']['criteria_met'] === 2
                && $stats['maturity']['criteria']['decisions']['current'] === 2
                && $stats['maturity']['criteria']['decisions']['target'] === 20
                && $stats['maturity']['criteria']['products']['target'] === 5
                && count($stats['products']) === 1
                && $stats['products'][0]['name'] === 'Produto com decisao medida'
                && $stats['products'][0]['total'] === 2
                && $stats['products'][0]['kept'] === 1
                && $stats['products'][0]['adjusted'] === 1
                && $stats['products'][0]['adherence_percent'] === 50.0
                && $stats['products'][0]['adjustment_rate_percent'] === 50.0
                && $stats['products'][0]['quantity_adjusted'] === 1
                && $stats['products'][0]['unit_cost_adjusted'] === 1
                && $stats['products'][0]['supplier_adjusted'] === 1
                && $stats['products'][0]['average_abs_quantity_delta_percent'] === 12.5
                && $stats['products'][0]['average_abs_unit_cost_delta_percent'] === 3.85);

        $this->get(route('purchase-entries.replenishment-purchases', ['classification' => 'adjusted']))
            ->assertOk()
            ->assertSeeText($entry->code)
            ->assertDontSeeText($keptEntry->code)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total'] === 2);

        $this->get(route('purchase-entries.replenishment-purchases', ['classification' => 'kept']))
            ->assertOk()
            ->assertSeeText($keptEntry->code)
            ->assertDontSeeText($entry->code);

        $this->get(route('purchase-entries.replenishment-purchases', ['q' => $entry->code]))
            ->assertOk()
            ->assertSeeText($entry->code)
            ->assertDontSeeText($keptEntry->code);

        $entry->update(['purchased_at' => now()->subDays(120)]);

        $this->get(route('purchase-entries.replenishment-purchases'))
            ->assertOk()
            ->assertDontSeeText($entry->code)
            ->assertSeeText($keptEntry->code)
            ->assertViewHas('filters', fn (array $filters): bool => $filters['period'] === '90')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['period'] === '90'
                && $stats['period_label'] === 'Últimos 90 dias'
                && $stats['total'] === 1
                && $stats['comparable'] === 1
                && $stats['kept'] === 1
                && $stats['adherence_percent'] === 100.0
                && $stats['products'][0]['total'] === 1
                && $stats['products'][0]['adjusted'] === 0);

        $this->get(route('purchase-entries.replenishment-purchases', ['period' => 'all']))
            ->assertOk()
            ->assertSeeText($entry->code)
            ->assertSeeText($keptEntry->code)
            ->assertSeeText('Todo o histórico, pela data da compra')
            ->assertViewHas('filters', fn (array $filters): bool => $filters['period'] === 'all')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['period'] === 'all'
                && $stats['total'] === 2
                && $stats['comparable'] === 2
                && $stats['adherence_percent'] === 50.0
                && $stats['products'][0]['total'] === 2
                && $stats['products'][0]['adjusted'] === 1);

        $report = $this->getJson(route('purchase-entries.replenishment-purchases.report', ['period' => 'all']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('schema_version', 1)
            ->assertJsonPath('scope.clinic_id', $clinic->id)
            ->assertJsonPath('scope.period', 'all')
            ->assertJsonPath('metrics.total', 2)
            ->assertJsonPath('metrics.comparable', 2)
            ->assertJsonPath('metrics.kept', 1)
            ->assertJsonPath('metrics.adjusted', 1)
            ->assertJsonPath('maturity.status', 'building')
            ->assertJsonPath('maturity.criteria.decisions.current', 2)
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Produto com decisao medida');

        $reportContent = $report->getContent();
        $this->assertStringNotContainsString($prefill['intelligence_metadata']['evidence']['signature'], $reportContent);
        $this->assertStringNotContainsString('intelligence_metadata', $reportContent);
        $this->assertStringNotContainsString('adjustment_reason_note', $reportContent);

        $this->getJson(route('purchase-entries.replenishment-purchases.report', ['period' => '365']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');

        $this->get(route('purchase-entries.replenishment-purchases', ['period' => '365']))
            ->assertSessionHasErrors('period');
    }

    public function test_invalid_replenishment_evidence_is_excluded_from_purchase_comparison(): void
    {
        $clinic = $this->clinic('Clinica Evidencia de Compra Invalida', '00000000000609');
        $product = $this->product($clinic, 'Produto com metadado alterado', stock: 2, minimum: 5, cost: 9);
        $user = $this->userForClinic($clinic);

        $this->actingAs($user);

        $suggestion = app(ReplenishmentSuggestionService::class)->suggestionFor($product);
        $prefill = $this->get($suggestion['purchase_url'])
            ->assertOk()
            ->viewData('suggestedItem');
        $prefill['intelligence_metadata']['evidence']['snapshot']['suggested_quantity'] = 900;

        $this->post(route('purchase-entries.store'), [
            'status' => 'draft',
            'purchased_at' => now()->format('Y-m-d'),
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 10,
                'unit_cost' => 9,
                'sale_price' => 18,
                'intelligence_status' => $prefill['intelligence_status'],
                'intelligence_metadata' => json_encode($prefill['intelligence_metadata'], JSON_THROW_ON_ERROR),
            ]],
        ])->assertRedirect(route('purchase-entries.index'));

        $entry = PurchaseEntry::query()->latest('id')->firstOrFail();
        $decision = $entry->items()->sole()->intelligence_metadata['replenishment_decision'];

        $this->assertSame('invalid', $decision['evidence_status']);
        $this->assertSame('unavailable', $decision['classification']);
        $this->assertArrayNotHasKey('quantity', $decision);
        $this->assertArrayNotHasKey('unit_cost', $decision);
        $this->assertArrayNotHasKey('supplier', $decision);

        $this->get(route('purchase-entries.replenishment-purchases'))
            ->assertOk()
            ->assertSeeText($entry->code)
            ->assertSeeText('Comparação indisponível')
            ->assertSeeText('Evidência inválida')
            ->assertSeeText('Sugestão indisponível')
            ->assertSeeText('Evidências indisponíveis')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total'] === 1
                && $stats['comparable'] === 0
                && $stats['unavailable'] === 1
                && $stats['product_count'] === 1
                && $stats['comparable_product_count'] === 0
                && $stats['products'][0]['unavailable'] === 1
                && $stats['evidence_coverage_percent'] === 0.0
                && $stats['maturity']['status'] === 'building'
                && $stats['adherence_percent'] === null
                && $stats['average_abs_quantity_delta_percent'] === null
                && $stats['average_abs_unit_cost_delta_percent'] === null);
    }

    public function test_product_breakdown_prioritizes_products_with_adjusted_decisions(): void
    {
        $clinic = $this->clinic('Clinica Analise por Produto', '00000000000610');
        $adjustedProduct = $this->product($clinic, 'Produto com maior divergencia', stock: 2, minimum: 5, cost: 9);
        $keptProduct = $this->product($clinic, 'Produto com decisao mantida', stock: 2, minimum: 5, cost: 9);
        $user = $this->userForClinic($clinic);
        $entry = PurchaseEntry::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'ENT-ANALISE-PRODUTO',
            'status' => 'draft',
            'purchased_at' => now(),
            'subtotal' => 27,
            'total' => 27,
        ]);

        foreach ([
            [$adjustedProduct, 'adjusted', 12.0, 10.0, true, 20.0],
            [$keptProduct, 'kept', 10.0, 10.0, false, 0.0],
        ] as [$product, $classification, $actual, $suggested, $changed, $deltaPercent]) {
            $entry->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $actual,
                'unit_cost' => 9,
                'total_cost' => $actual * 9,
                'intelligence_status' => 'replenishment_suggestion',
                'intelligence_metadata' => [
                    'replenishment_decision' => [
                        'evidence_status' => 'valid',
                        'classification' => $classification,
                        'quantity' => [
                            'suggested' => $suggested,
                            'actual' => $actual,
                            'delta_percent' => $deltaPercent,
                            'changed' => $changed,
                        ],
                        'unit_cost' => [
                            'suggested' => 9,
                            'actual' => 9,
                            'delta_percent' => 0,
                            'changed' => false,
                        ],
                        'supplier' => ['status' => 'unavailable'],
                    ],
                ],
            ]);
        }

        $stats = app(ReplenishmentPurchaseHistoryService::class)->summary($user);

        $this->assertSame(2, $stats['product_count']);
        $this->assertCount(2, $stats['products']);
        $this->assertSame('Produto com maior divergencia', $stats['products'][0]['name']);
        $this->assertSame(1, $stats['products'][0]['adjusted']);
        $this->assertSame(100.0, $stats['products'][0]['adjustment_rate_percent']);
        $this->assertSame(20.0, $stats['products'][0]['average_abs_quantity_delta_percent']);
        $this->assertSame('Produto com decisao mantida', $stats['products'][1]['name']);
        $this->assertSame(1, $stats['products'][1]['kept']);
        $this->assertSame(100.0, $stats['products'][1]['adherence_percent']);
    }

    public function test_pilot_sample_reaches_the_advisory_operational_reference(): void
    {
        $clinic = $this->clinic('Clinica Amostra Madura', '00000000000613');
        $user = $this->userForClinic($clinic);
        $entry = PurchaseEntry::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'ENT-AMOSTRA-MADURA',
            'status' => 'draft',
            'purchased_at' => now(),
            'subtotal' => 1800,
            'total' => 1800,
        ]);

        for ($productIndex = 1; $productIndex <= 5; $productIndex++) {
            $product = $this->product(
                $clinic,
                'Produto da amostra '.$productIndex,
                stock: 2,
                minimum: 5,
                cost: 9,
            );

            for ($decisionIndex = 1; $decisionIndex <= 4; $decisionIndex++) {
                $entry->items()->create([
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 10,
                    'unit_cost' => 9,
                    'total_cost' => 90,
                    'intelligence_status' => 'replenishment_suggestion',
                    'intelligence_metadata' => [
                        'replenishment_decision' => [
                            'evidence_status' => 'valid',
                            'classification' => 'kept',
                            'quantity' => [
                                'suggested' => 10,
                                'actual' => 10,
                                'delta_percent' => 0,
                                'changed' => false,
                            ],
                            'unit_cost' => [
                                'suggested' => 9,
                                'actual' => 9,
                                'delta_percent' => 0,
                                'changed' => false,
                            ],
                            'supplier' => ['status' => 'unavailable'],
                        ],
                    ],
                ]);
            }
        }

        $stats = app(ReplenishmentPurchaseHistoryService::class)->summary($user);

        $this->assertSame(20, $stats['comparable']);
        $this->assertSame(5, $stats['comparable_product_count']);
        $this->assertSame(100.0, $stats['evidence_coverage_percent']);
        $this->assertNull($stats['adjustment_reason_coverage_percent']);
        $this->assertSame('ready', $stats['maturity']['status']);
        $this->assertSame(4, $stats['maturity']['criteria_met']);
        $this->assertTrue($stats['maturity']['criteria']['reasons']['met']);

        $this->actingAs($user)
            ->get(route('purchase-entries.replenishment-purchases'))
            ->assertOk()
            ->assertSeeText('Base operacional atingida')
            ->assertSeeText('4 de 4 critérios atendidos')
            ->assertSeeText('Não se aplica')
            ->assertSeeText('faça revisão humana antes de alterar qualquer regra');
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

        $externalEntry = PurchaseEntry::query()->create([
            'clinic_id' => $clinicB->id,
            'code' => 'ENT-DECISAO-EXTERNA',
            'status' => 'draft',
            'purchased_at' => now(),
            'subtotal' => 5,
            'total' => 5,
        ]);
        $externalEntry->items()->create([
            'product_id' => $productB->id,
            'description' => $productB->name,
            'quantity' => 1,
            'unit_cost' => 5,
            'total_cost' => 5,
            'intelligence_status' => 'replenishment_suggestion',
            'intelligence_metadata' => [
                'replenishment_decision' => [
                    'version' => 1,
                    'evidence_status' => 'valid',
                    'classification' => 'adjusted',
                    'quantity' => ['suggested' => 2, 'actual' => 1, 'delta' => -1, 'delta_percent' => -50],
                    'unit_cost' => ['suggested' => 4, 'actual' => 5, 'delta' => 1, 'delta_percent' => 25],
                    'supplier' => ['suggested_id' => null, 'actual_id' => null, 'status' => 'unavailable'],
                    'evaluated_at' => now()->toISOString(),
                ],
            ],
        ]);

        $this->actingAs($userA);

        $this->get(route('purchase-entries.replenishment-reviews'))
            ->assertOk()
            ->assertDontSeeText('Produto externo da revisao')
            ->assertDontSeeText('Registro exclusivo da clinica B.');

        $this->get(route('purchase-entries.replenishment-purchases'))
            ->assertOk()
            ->assertDontSeeText('ENT-DECISAO-EXTERNA')
            ->assertDontSeeText('Produto externo da revisao')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total'] === 0
                && $stats['comparable'] === 0
                && $stats['unavailable'] === 0
                && $stats['product_count'] === 0
                && $stats['products'] === []);

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
