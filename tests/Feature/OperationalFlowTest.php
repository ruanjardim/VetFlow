<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Commissions\Models\CommissionRule;
use App\Modules\Commissions\Services\CommissionService;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleEvent;
use App\Modules\Sales\Services\SaleService;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sale_applies_stock_and_financial_records_for_current_clinic(): void
    {
        $clinic = $this->clinic('Clinica Operacional A', '00000000000201');
        $product = $this->product($clinic, 'Vacina V10', stock: 10, costPrice: 8, salePrice: 25);
        $user = $this->userForClinic($clinic, ['sales.manage']);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Vacina V10',
                    'quantity' => '2',
                    'unit_price' => '25',
                ],
            ],
            'payments' => [
                [
                    'method' => 'pix',
                    'amount' => '50',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('sales.index'))
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->with('financialTransaction')->firstOrFail();
        $movement = InventoryMovement::query()->where('sale_id', $sale->id)->firstOrFail();
        $financialTransaction = $sale->financialTransaction;

        $this->assertSame($clinic->id, (int) $sale->clinic_id);
        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertTrue($sale->stock_applied);
        $this->assertTrue($sale->financial_applied);
        $this->assertEquals(50.0, (float) $sale->total);
        $this->assertEquals(8.0, (float) $product->fresh()->stock_quantity);

        $this->assertSame($clinic->id, (int) $movement->clinic_id);
        $this->assertSame('exit', $movement->type);
        $this->assertSame('sale', $movement->source);
        $this->assertEquals(10.0, (float) $movement->balance_before);
        $this->assertEquals(8.0, (float) $movement->balance_after);

        $this->assertNotNull($financialTransaction);
        $this->assertSame($clinic->id, (int) $financialTransaction->clinic_id);
        $this->assertSame('income', $financialTransaction->type);
        $this->assertSame('paid', $financialTransaction->status);
        $this->assertEquals(50.0, (float) $financialTransaction->amount);
    }

    public function test_pending_payment_only_counts_after_a_later_receipt_is_registered(): void
    {
        $clinic = $this->clinic('Clinica Recebimentos', '00000000000202');
        $product = $this->product($clinic, 'Antiparasitario', stock: 5, costPrice: 12, salePrice: 50);
        $user = $this->userForClinic($clinic, ['sales.manage']);

        $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Antiparasitario',
                    'quantity' => '1',
                    'unit_price' => '50',
                ],
            ],
            'payments' => [
                [
                    'method' => 'pix',
                    'amount' => '50',
                    'status' => 'pending',
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->with(['payments', 'financialTransaction'])->firstOrFail();

        $this->assertSame('pending', $sale->payment_status);
        $this->assertEquals(0.0, (float) $sale->paid_total);
        $this->assertNull($sale->payments->first()->paid_at);
        $this->assertSame('pending', $sale->financialTransaction->status);

        $summary = app(SaleService::class)->cashierSummary(today()->toDateString(), today()->toDateString());

        $this->assertEquals(0.0, (float) $summary['stats']['received']);
        $this->assertEquals(50.0, (float) $summary['stats']['pending']);

        $this->post(route('sales.payments.store', $sale->id), [
            'method' => 'pix',
            'amount' => '20,00',
            'reference' => 'PIX-PARCIAL-1',
        ])->assertRedirect(route('sales.edit', $sale->id));

        $sale->refresh()->load(['payments', 'financialTransaction']);

        $this->assertSame('partial', $sale->payment_status);
        $this->assertEquals(20.0, (float) $sale->paid_total);
        $this->assertSame('pending', $sale->financialTransaction->status);
        $this->assertSame(1, $sale->payments->where('status', 'paid')->count());
        $this->assertDatabaseHas('sale_events', [
            'sale_id' => $sale->id,
            'event_type' => 'payment_received',
            'amount' => 20,
        ]);

        $this->post(route('sales.payments.store', $sale->id), [
            'method' => 'cash',
            'amount' => '30',
            'reference' => 'CAIXA-1',
        ])->assertRedirect(route('sales.edit', $sale->id));

        $sale->refresh()->load(['payments', 'financialTransaction']);

        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(50.0, (float) $sale->paid_total);
        $this->assertSame('paid', $sale->financialTransaction->status);
        $this->assertNotNull($sale->financialTransaction->paid_at);

        $summary = app(SaleService::class)->cashierSummary(today()->toDateString(), today()->toDateString());

        $this->assertEquals(50.0, (float) $summary['stats']['received']);
        $this->assertEquals(30.0, (float) $summary['stats']['cash_received']);
        $this->assertEquals(0.0, (float) $summary['stats']['pending']);
    }

    public function test_cashier_summary_groups_sales_and_receipts_by_seller(): void
    {
        $clinic = $this->clinic('Clinica Operadores', '00000000000203');
        $product = $this->product($clinic, 'Servico com operador', stock: 10, costPrice: 10, salePrice: 30);
        $sellerA = $this->userForClinic($clinic, ['sales.manage']);
        $sellerB = $this->userForClinic($clinic, ['sales.manage']);

        $this->actingAs($sellerA)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => 'Servico com operador',
                'quantity' => '1',
                'unit_price' => '30',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '30',
            ]],
        ])->assertRedirect(route('sales.index'));

        $this->actingAs($sellerB)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => 'Servico com operador',
                'quantity' => '1',
                'unit_price' => '30',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '30',
                'status' => 'pending',
            ]],
        ])->assertRedirect(route('sales.index'));

        $saleB = Sale::query()->where('seller_user_id', $sellerB->id)->firstOrFail();

        $this->actingAs($sellerB)->post(route('sales.payments.store', $saleB->id), [
            'method' => 'cash',
            'amount' => '10',
        ])->assertRedirect(route('sales.edit', $saleB->id));

        $summary = app(SaleService::class)->cashierSummary(today()->toDateString(), today()->toDateString());
        $performance = collect($summary['seller_performance'])->keyBy('seller_user_id');

        $this->assertEquals(30.0, (float) $performance[$sellerA->id]['sold_total']);
        $this->assertEquals(30.0, (float) $performance[$sellerA->id]['received']);
        $this->assertEquals(0.0, (float) $performance[$sellerA->id]['pending']);
        $this->assertEquals(20.0, (float) $performance[$sellerA->id]['gross_profit']);

        $this->assertEquals(30.0, (float) $performance[$sellerB->id]['sold_total']);
        $this->assertEquals(10.0, (float) $performance[$sellerB->id]['received']);
        $this->assertEquals(20.0, (float) $performance[$sellerB->id]['pending']);
        $this->assertEquals(20.0, (float) $performance[$sellerB->id]['gross_profit']);
    }

    public function test_commission_preview_uses_paid_sales_and_partial_receipts_according_to_each_rule(): void
    {
        $clinic = $this->clinic('Clinica Comissoes', '00000000000204');
        $product = $this->product($clinic, 'Produto comissao', stock: 10, costPrice: 60, salePrice: 100);
        $administrator = $this->userForClinic($clinic, ['sales.manage', 'commissions.manage']);
        $sellerA = $this->userForClinic($clinic, ['sales.manage']);
        $sellerB = $this->userForClinic($clinic, ['sales.manage']);

        $this->actingAs($sellerA)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => 'Produto comissao',
                'quantity' => '1',
                'unit_price' => '100',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '100',
            ]],
        ])->assertRedirect(route('sales.index'));

        $this->actingAs($sellerB)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => 'Produto comissao',
                'quantity' => '1',
                'unit_price' => '100',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '100',
                'status' => 'pending',
            ]],
        ])->assertRedirect(route('sales.index'));

        $pendingSale = Sale::query()->where('seller_user_id', $sellerB->id)->firstOrFail();

        $this->actingAs($sellerB)->post(route('sales.payments.store', $pendingSale->id), [
            'method' => 'pix',
            'amount' => '50',
            'paid_at' => now()->format('Y-m-d\\TH:i'),
        ])->assertRedirect(route('sales.edit', $pendingSale->id));

        $this->actingAs($administrator)->post(route('commissions.store'), [
            'seller_user_id' => $sellerA->id,
            'name' => 'Margem vendedor A',
            'percentage' => '10,00',
            'basis' => 'gross_profit',
            'recognition' => 'sale_date',
            'requires_paid' => '1',
            'starts_on' => today()->toDateString(),
            'active' => '1',
        ])->assertRedirect(route('commissions.index'));

        $this->post(route('commissions.store'), [
            'seller_user_id' => $sellerB->id,
            'name' => 'Recebimentos vendedor B',
            'percentage' => '10',
            'basis' => 'sold_total',
            'recognition' => 'receipt_date',
            'requires_paid' => '0',
            'starts_on' => today()->toDateString(),
            'active' => '1',
        ])->assertRedirect(route('commissions.index'));

        $preview = app(CommissionService::class)->preview(today()->toDateString(), today()->toDateString());
        $rows = $preview['rules']->keyBy(fn (array $row) => $row['rule']->seller_user_id);

        $this->assertEquals(40.0, (float) $rows[$sellerA->id]['base_amount']);
        $this->assertEquals(4.0, (float) $rows[$sellerA->id]['commission_amount']);
        $this->assertEquals(50.0, (float) $rows[$sellerB->id]['base_amount']);
        $this->assertEquals(5.0, (float) $rows[$sellerB->id]['commission_amount']);
        $this->assertSame(2, $preview['summary']['rules_count']);

        $this->post(route('commissions.store'), [
            'seller_user_id' => $sellerA->id,
            'name' => 'Regra concorrente',
            'percentage' => '5',
            'basis' => 'sold_total',
            'recognition' => 'sale_date',
            'requires_paid' => '1',
            'starts_on' => today()->toDateString(),
            'active' => '1',
        ])->assertSessionHasErrors('starts_on');

        $this->assertSame(2, CommissionRule::query()->count());
    }

    public function test_global_user_sale_keeps_financial_record_inside_selected_clinic(): void
    {
        $clinic = $this->clinic('Clinica Venda Global A', '00000000000261');
        $product = $this->product($clinic, 'Vermifugo global', stock: 10, costPrice: 9, salePrice: 45);
        $user = $this->globalUser(['sales.manage']);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'clinic_id' => $clinic->id,
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Vermifugo global',
                    'quantity' => '1',
                    'unit_price' => '45',
                ],
            ],
            'payments' => [
                [
                    'method' => 'pix',
                    'amount' => '45',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('sales.index'))
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->with(['financialTransaction'])->firstOrFail();

        $this->assertSame($clinic->id, (int) $sale->clinic_id);
        $this->assertNotNull($sale->financialTransaction);
        $this->assertSame($clinic->id, (int) $sale->financialTransaction->clinic_id);
    }

    public function test_global_user_sale_rejects_product_outside_selected_clinic(): void
    {
        $clinicA = $this->clinic('Clinica Venda Global B', '00000000000262');
        $clinicB = $this->clinic('Clinica Venda Global C', '00000000000263');
        $externalProduct = $this->product($clinicB, 'Produto global externo', stock: 5, salePrice: 30);
        $user = $this->globalUser(['sales.manage']);

        $response = $this
            ->actingAs($user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'clinic_id' => $clinicA->id,
                'status' => 'completed',
                'items' => [
                    [
                        'type' => 'product',
                        'product_id' => $externalProduct->id,
                        'description' => 'Tentativa produto global externo',
                        'quantity' => '1',
                        'unit_price' => '30',
                    ],
                ],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => '30',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('sales.create'))
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertEquals(5.0, (float) $externalProduct->fresh()->stock_quantity);
    }

    public function test_sale_rejects_product_from_another_clinic_before_side_effects(): void
    {
        $clinicA = $this->clinic('Clinica Operacional B', '00000000000211');
        $clinicB = $this->clinic('Clinica Operacional C', '00000000000212');
        $productFromOtherClinic = $this->product($clinicB, 'Produto de outra clinica', stock: 6, salePrice: 30);
        $user = $this->userForClinic($clinicA, ['sales.manage']);

        $response = $this
            ->actingAs($user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'status' => 'completed',
                'items' => [
                    [
                        'type' => 'product',
                        'product_id' => $productFromOtherClinic->id,
                        'description' => 'Tentativa produto externo',
                        'quantity' => '1',
                        'unit_price' => '30',
                    ],
                ],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => '30',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('sales.create'))
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_cancelled_sale_restores_stock_cancels_financial_record_and_is_idempotent(): void
    {
        $clinic = $this->clinic('Clinica Cancelamento A', '00000000000241');
        $product = $this->product($clinic, 'Antipulgas', stock: 10, costPrice: 12, salePrice: 35);
        $user = $this->userForClinic($clinic, ['sales.manage']);

        $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Antipulgas',
                    'quantity' => '2',
                    'unit_price' => '35',
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => '70',
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->with(['items', 'financialTransaction', 'payments'])->firstOrFail();

        $this->assertEquals(8.0, (float) $product->fresh()->stock_quantity);

        $this->patch(route('sales.cancel', $sale->id), [
            'reason' => 'Cliente desistiu',
        ])->assertRedirect(route('sales.index'));

        $sale->refresh()->load(['items', 'financialTransaction', 'payments']);
        $stockReturn = InventoryMovement::query()
            ->where('sale_id', $sale->id)
            ->where('source', 'sale_cancellation')
            ->firstOrFail();

        $this->assertSame('cancelled', $sale->status);
        $this->assertSame('cancelled', $sale->payment_status);
        $this->assertSame('Cliente desistiu', $sale->cancellation_reason);
        $this->assertEquals(70.0, (float) $sale->return_total);
        $this->assertEquals(70.0, (float) $sale->refunded_total);
        $this->assertEquals(10.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame('entry', $stockReturn->type);
        $this->assertEquals(2.0, (float) $stockReturn->quantity);
        $this->assertEquals(10.0, (float) $stockReturn->balance_after);
        $this->assertSame('cancelled', $sale->financialTransaction->status);
        $this->assertNull($sale->financialTransaction->paid_at);
        $this->assertTrue($sale->payments->every(fn ($payment) => $payment->status === 'cancelled'));
        $this->assertEquals(2.0, (float) $sale->items->first()->returned_quantity);
        $this->assertEquals(70.0, (float) $sale->items->first()->refunded_total);
        $this->assertDatabaseHas('sale_events', [
            'sale_id' => $sale->id,
            'event_type' => 'cancelled',
        ]);
        $this->assertDatabaseHas('sale_events', [
            'sale_id' => $sale->id,
            'event_type' => 'stock_reversal',
        ]);

        $this->patch(route('sales.cancel', $sale->id), [
            'reason' => 'Nova tentativa',
        ])->assertRedirect(route('sales.index'));

        $this->assertSame(2, InventoryMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertEquals(10.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_partial_sale_return_restores_stock_and_records_refund_without_cancelling_income(): void
    {
        $clinic = $this->clinic('Clinica Devolucao A', '00000000000242');
        $product = $this->product($clinic, 'Racao retorno', stock: 10, costPrice: 8, salePrice: 20);
        $user = $this->userForClinic($clinic, ['sales.manage']);

        $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Racao retorno',
                    'quantity' => '3',
                    'unit_price' => '20',
                ],
            ],
            'payments' => [
                [
                    'method' => 'pix',
                    'amount' => '60',
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->with(['items', 'financialTransaction'])->firstOrFail();
        $item = $sale->items->first();

        $this->assertEquals(7.0, (float) $product->fresh()->stock_quantity);

        $this->post(route('sales.returns.store', $sale->id), [
            'reason' => 'Devolucao parcial',
            'refund_method' => 'pix',
            'refund_amount' => '20',
            'reference' => 'PIX-DEV-1',
            'items' => [
                $item->id => [
                    'quantity' => '1',
                ],
            ],
        ])->assertRedirect(route('sales.edit', $sale->id));

        $sale->refresh()->load(['items', 'financialTransaction']);
        $refund = FinancialTransaction::query()
            ->where('type', 'expense')
            ->where('description', 'Estorno venda '.$sale->code)
            ->firstOrFail();
        $stockReturn = InventoryMovement::query()
            ->where('sale_id', $sale->id)
            ->where('source', 'sale_return')
            ->firstOrFail();

        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(20.0, (float) $sale->return_total);
        $this->assertEquals(20.0, (float) $sale->refunded_total);
        $this->assertEquals(8.0, (float) $product->fresh()->stock_quantity);
        $this->assertEquals(1.0, (float) $sale->items->first()->returned_quantity);
        $this->assertEquals(20.0, (float) $sale->items->first()->refunded_total);
        $this->assertSame('paid', $sale->financialTransaction->status);
        $this->assertSame($clinic->id, (int) $refund->clinic_id);
        $this->assertSame('paid', $refund->status);
        $this->assertSame('pix', $refund->payment_method);
        $this->assertSame('PIX-DEV-1', $refund->reference);
        $this->assertEquals(20.0, (float) $refund->amount);
        $this->assertSame('entry', $stockReturn->type);
        $this->assertEquals(1.0, (float) $stockReturn->quantity);
        $this->assertEquals(8.0, (float) $stockReturn->balance_after);
        $this->assertSame(1, SaleEvent::query()->where('sale_id', $sale->id)->where('event_type', 'refund')->count());
        $this->assertSame(1, SaleEvent::query()->where('sale_id', $sale->id)->where('event_type', 'partial_return')->count());
        $this->assertSame(1, SaleEvent::query()->where('sale_id', $sale->id)->where('event_type', 'stock_return')->count());
    }

    public function test_cashier_summary_ignores_refunds_from_other_clinics(): void
    {
        $clinicA = $this->clinic('Clinica Caixa A', '00000000000251');
        $clinicB = $this->clinic('Clinica Caixa B', '00000000000252');
        $productA = $this->product($clinicA, 'Shampoo caixa A', stock: 10, costPrice: 10, salePrice: 30);
        $productB = $this->product($clinicB, 'Shampoo caixa B', stock: 10, costPrice: 10, salePrice: 40);
        $userA = $this->userForClinic($clinicA, ['sales.manage']);
        $userB = $this->userForClinic($clinicB, ['sales.manage']);

        $this->actingAs($userA)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $productA->id,
                    'description' => 'Shampoo caixa A',
                    'quantity' => '2',
                    'unit_price' => '30',
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => '60',
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $saleA = Sale::query()->with('items')->firstOrFail();

        $this->post(route('sales.returns.store', $saleA->id), [
            'reason' => 'Devolucao local',
            'refund_method' => 'cash',
            'refund_amount' => '30',
            'items' => [
                $saleA->items->first()->id => [
                    'quantity' => '1',
                ],
            ],
        ])->assertRedirect(route('sales.edit', $saleA->id));

        $this->actingAs($userB)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $productB->id,
                    'description' => 'Shampoo caixa B',
                    'quantity' => '2',
                    'unit_price' => '40',
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => '80',
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $saleB = Sale::query()->with('items')->firstOrFail();

        $this->post(route('sales.returns.store', $saleB->id), [
            'reason' => 'Devolucao externa',
            'refund_method' => 'cash',
            'refund_amount' => '40',
            'items' => [
                $saleB->items->first()->id => [
                    'quantity' => '1',
                ],
            ],
        ])->assertRedirect(route('sales.edit', $saleB->id));

        $this->actingAs($userA);

        $summary = app(SaleService::class)->cashierSummary(today()->toDateString(), today()->toDateString());

        $this->assertSame(1, $summary['stats']['sales_count']);
        $this->assertEquals(60.0, (float) $summary['stats']['received']);
        $this->assertEquals(30.0, (float) $summary['stats']['refunds']);
        $this->assertEquals(30.0, (float) $summary['stats']['cash_refunds']);
        $this->assertEquals(30.0, (float) $summary['stats']['net_received']);
        $this->assertEquals(30.0, (float) $summary['stats']['cash_drawer']);
        $this->assertSame($saleA->id, $summary['recent_sales']->first()->id);
    }

    public function test_inventory_movement_updates_stock_and_rejects_cross_clinic_product(): void
    {
        $clinicA = $this->clinic('Clinica Operacional D', '00000000000221');
        $clinicB = $this->clinic('Clinica Operacional E', '00000000000222');
        $ownProduct = $this->product($clinicA, 'Soro fisiologico', stock: 5);
        $otherProduct = $this->product($clinicB, 'Produto externo estoque', stock: 4);
        $user = $this->userForClinic($clinicA, ['inventory.manage']);

        $response = $this->actingAs($user)->post(route('inventory-movements.store'), [
            'product_id' => $ownProduct->id,
            'type' => 'entry',
            'quantity' => '3',
            'unit_cost' => '12.50',
            'reason' => 'Entrada manual',
        ]);

        $response
            ->assertRedirect(route('inventory-movements.index'))
            ->assertSessionDoesntHaveErrors();

        $movement = InventoryMovement::query()->where('product_id', $ownProduct->id)->firstOrFail();

        $this->assertSame($clinicA->id, (int) $movement->clinic_id);
        $this->assertSame('entry', $movement->type);
        $this->assertEquals(5.0, (float) $movement->balance_before);
        $this->assertEquals(8.0, (float) $movement->balance_after);
        $this->assertEquals(8.0, (float) $ownProduct->fresh()->stock_quantity);

        $response = $this
            ->from(route('inventory-movements.create'))
            ->post(route('inventory-movements.store'), [
                'product_id' => $otherProduct->id,
                'type' => 'exit',
                'quantity' => '1',
                'reason' => 'Tentativa produto externo',
            ]);

        $response
            ->assertRedirect(route('inventory-movements.create'))
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseMissing('inventory_movements', [
            'clinic_id' => $clinicA->id,
            'product_id' => $otherProduct->id,
        ]);
        $this->assertEquals(
            4.0,
            (float) DB::table('products')->where('id', $otherProduct->id)->value('stock_quantity')
        );
    }

    public function test_inventory_movements_created_by_sales_cannot_be_changed_from_stock_screen(): void
    {
        $clinic = $this->clinic('Clinica Rastreabilidade Estoque', '00000000000223');
        $product = $this->product($clinic, 'Medicamento rastreado', stock: 5, costPrice: 10, salePrice: 30);
        $user = $this->userForClinic($clinic, ['inventory.manage', 'sales.manage']);

        $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => '1',
                'unit_price' => '30',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '30',
                'installments' => 1,
            ]],
        ])->assertRedirect(route('sales.index'));

        $movement = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('source', 'sale')
            ->firstOrFail();

        $this->get(route('inventory-movements.edit', $movement->id))
            ->assertRedirect(route('inventory-movements.index'))
            ->assertSessionHas('error');

        $this->patch(route('inventory-movements.update', $movement->id), [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => '5',
        ])->assertRedirect(route('inventory-movements.index'))
            ->assertSessionHas('error');

        $this->delete(route('inventory-movements.destroy', $movement->id))
            ->assertRedirect(route('inventory-movements.index'))
            ->assertSessionHas('error');

        $this->assertSame('exit', $movement->fresh()->type);
        $this->assertEquals(4.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_financial_transactions_are_created_and_updated_only_inside_current_clinic(): void
    {
        $clinicA = $this->clinic('Clinica Operacional F', '00000000000231');
        $clinicB = $this->clinic('Clinica Operacional G', '00000000000232');
        $ownSupplier = $this->supplier($clinicA, 'Fornecedor local');
        $otherSupplier = $this->supplier($clinicB, 'Fornecedor externo');
        $ownTransaction = $this->financialTransaction($clinicA, 'Recebimento local');
        $otherTransaction = $this->financialTransaction($clinicB, 'Recebimento externo');
        $user = $this->userForClinic($clinicA, ['financial.manage']);

        $response = $this->actingAs($user)->post(route('financial-transactions.store'), [
            'supplier_id' => $ownSupplier->id,
            'type' => 'expense',
            'description' => 'Compra local',
            'amount' => '125.90',
            'due_date' => today()->toDateString(),
            'status' => 'pending',
        ]);

        $response
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionDoesntHaveErrors();

        $created = FinancialTransaction::query()
            ->where('description', 'Compra local')
            ->firstOrFail();

        $this->assertSame($clinicA->id, (int) $created->clinic_id);
        $this->assertSame($ownSupplier->id, (int) $created->supplier_id);

        $response = $this
            ->from(route('financial-transactions.create'))
            ->post(route('financial-transactions.store'), [
                'supplier_id' => $otherSupplier->id,
                'type' => 'expense',
                'description' => 'Compra externa indevida',
                'amount' => '99.90',
                'status' => 'pending',
            ]);

        $response
            ->assertRedirect(route('financial-transactions.create'))
            ->assertSessionHasErrors('supplier_id');

        $this->patch(route('financial-transactions.pay', $ownTransaction->id))
            ->assertRedirect(route('financial-transactions.index'));

        $this->assertSame('paid', $ownTransaction->fresh()->status);

        $this->patch(route('financial-transactions.pay', $otherTransaction->id))
            ->assertNotFound();

        $this->assertSame(
            'pending',
            DB::table('financial_transactions')->where('id', $otherTransaction->id)->value('status')
        );
    }

    public function test_sale_generated_income_is_managed_only_from_the_sale_flow(): void
    {
        $clinic = $this->clinic('Clinica Financeiro PDV', '00000000000233');
        $product = $this->product($clinic, 'Produto recebido depois', stock: 4, costPrice: 20, salePrice: 60);
        $user = $this->userForClinic($clinic, ['sales.manage', 'financial.manage']);

        $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => 'Produto recebido depois',
                'quantity' => '1',
                'unit_price' => '60',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '60',
                'status' => 'pending',
            ]],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->with('financialTransaction')->firstOrFail();
        $transaction = $sale->financialTransaction;

        $this->assertNotNull($transaction);

        $this->get(route('financial-transactions.index'))
            ->assertOk()
            ->assertSee('Gerenciado pela venda '.$sale->code.'.')
            ->assertSee(route('sales.edit', $sale->id));

        $this->get(route('financial-transactions.edit', $transaction->id))
            ->assertRedirect(route('sales.edit', $sale->id))
            ->assertSessionHas('error');

        $this->from(route('financial-transactions.index'))
            ->patch(route('financial-transactions.update', $transaction->id), [
                'type' => 'income',
                'description' => 'Tentativa de ajuste indevido',
                'amount' => '60',
                'status' => 'paid',
            ])
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionHasErrors('transaction');

        $this->from(route('financial-transactions.index'))
            ->patch(route('financial-transactions.pay', $transaction->id))
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionHasErrors('transaction');

        $this->from(route('financial-transactions.index'))
            ->patch(route('financial-transactions.cancel', $transaction->id))
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionHasErrors('transaction');

        $this->from(route('financial-transactions.index'))
            ->delete(route('financial-transactions.destroy', $transaction->id))
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionHasErrors('transaction');

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertNull($transaction->paid_at);
        $this->assertNull($transaction->deleted_at);
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

    private function globalUser(array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => null,
        ]);

        $this->grantPermissions($user, $permissionSlugs);

        return $user;
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

    private function supplier(Clinic $clinic, string $name): Supplier
    {
        return Supplier::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function financialTransaction(Clinic $clinic, string $description): FinancialTransaction
    {
        return FinancialTransaction::query()->create([
            'clinic_id' => $clinic->id,
            'type' => 'income',
            'description' => $description,
            'amount' => 80,
            'due_date' => today()->toDateString(),
            'status' => 'pending',
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
