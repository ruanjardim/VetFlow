<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleQuote;
use App\Modules\Sales\Services\SaleProfitabilityService;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleQuotesAndSaleTypeTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $user;

    private Product $product;

    private PetShopService $service;

    private Tutor $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->clinic('Clinica Orcamento', '00000000000501');
        $this->user = $this->userForClinic($this->clinic, ['sales.manage']);
        $this->product = Product::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Ração adulto 15kg',
            'cost_price' => 30,
            'sale_price' => 50,
            'stock_quantity' => 5,
            'active' => true,
        ]);
        $this->service = PetShopService::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Banho completo',
            'base_price' => 80,
            'active' => true,
        ]);
        $this->tutor = Tutor::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Maria Tutora',
            'phone' => '(21) 3333-4444',
            'phone_secondary' => '(21) 99999-8888',
            'street' => 'Rua das Flores',
            'number' => '10',
            'district' => 'Centro',
            'city' => 'Niterói',
            'state' => 'RJ',
            'zip_code' => '24000-000',
            'active' => true,
        ]);
    }

    public function test_quote_saved_from_the_pdv_moves_no_stock_money_or_sale(): void
    {
        $response = $this->actingAs($this->user)->post(route('sales.quotes.store'), $this->quotePayload([
            'discount_total' => '10,00',
            'notes' => 'Retirar na loja',
        ]));

        $quote = SaleQuote::query()->with('items')->firstOrFail();
        $response->assertRedirect(route('sales.quotes.show', $quote->id));

        $this->assertSame('ORC-000001', $quote->code);
        $this->assertSame('open', $quote->status);
        $this->assertSame($this->clinic->id, $quote->clinic_id);
        $this->assertSame($this->user->id, $quote->seller_user_id);
        $this->assertSame(today()->addDays(7)->toDateString(), $quote->valid_until->toDateString());
        $this->assertCount(2, $quote->items);
        $this->assertEquals(165, (float) $quote->subtotal);
        $this->assertEquals(155, (float) $quote->total);
        $this->assertSame('Retirar na loja', $quote->notes);

        $this->assertEquals(5, (float) $this->product->fresh()->stock_quantity);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_quote_requires_items_and_rejects_items_from_another_clinic(): void
    {
        $otherClinic = $this->clinic('Outra Clinica', '00000000000502');
        $foreignProduct = Product::query()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Produto de fora',
            'sale_price' => 10,
            'stock_quantity' => 1,
            'active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('sales.create', ['mode' => 'quote']))
            ->post(route('sales.quotes.store'), ['items' => []])
            ->assertSessionHasErrors('items');

        $this->from(route('sales.create', ['mode' => 'quote']))
            ->post(route('sales.quotes.store'), [
                'items' => [[
                    'type' => 'product',
                    'product_id' => $foreignProduct->id,
                    'description' => $foreignProduct->name,
                    'quantity' => '1',
                    'unit_price' => '10',
                ]],
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sale_quotes', 0);
    }

    public function test_quote_list_filters_by_open_expired_converted_and_cancelled(): void
    {
        $open = $this->createQuote();
        $expired = $this->createQuote(['valid_until' => today()->subDay()->toDateString()]);
        $cancelled = $this->createQuote();
        $cancelled->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $converted = $this->createQuote();
        $converted->update(['status' => 'converted', 'converted_at' => now()]);

        $this->actingAs($this->user);

        $this->get(route('sales.quotes.index', ['status' => 'open']))
            ->assertOk()->assertSee($open->code)
            ->assertDontSee($expired->code)->assertDontSee($cancelled->code)->assertDontSee($converted->code);
        $this->get(route('sales.quotes.index', ['status' => 'expired']))
            ->assertOk()->assertSee($expired->code)->assertSee('Vencido')->assertDontSee($open->code);
        $this->get(route('sales.quotes.index', ['status' => 'cancelled']))
            ->assertOk()->assertSee($cancelled->code)->assertDontSee($open->code);
        $this->get(route('sales.quotes.index', ['status' => 'converted']))
            ->assertOk()->assertSee($converted->code)->assertDontSee($open->code);
        $this->get(route('sales.quotes.index', ['q' => 'Maria']))
            ->assertOk()->assertSee($open->code)->assertSee($expired->code);
    }

    public function test_converting_a_quote_prefills_the_pdv_and_completion_marks_it_converted(): void
    {
        $quote = $this->createQuote(['discount_total' => '5']);

        $this->actingAs($this->user)
            ->get(route('sales.create', ['quote_id' => $quote->id]))
            ->assertOk()
            ->assertSee('Convertendo o orçamento')
            ->assertSee($quote->code)
            ->assertSee('name="sale_quote_id" value="'.$quote->id.'"', false)
            ->assertSee('Banho completo');

        $this->post(route('sales.store'), $this->salePayload($quote, 160))
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->firstOrFail();
        $quote->refresh();

        $this->assertSame('completed', $sale->status);
        $this->assertSame($quote->id, $sale->sale_quote_id);
        $this->assertSame('converted', $quote->status);
        $this->assertSame($sale->id, $quote->converted_sale_id);
        $this->assertNotNull($quote->converted_at);
        $this->assertEquals(3, (float) $this->product->fresh()->stock_quantity);

        $this->get(route('sales.create', ['quote_id' => $quote->id]))
            ->assertOk()
            ->assertSee('O orçamento informado não está aberto');
        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->salePayload($quote, 160))
            ->assertSessionHasErrors('sale_quote_id');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_cancelling_the_converted_sale_reopens_the_quote(): void
    {
        $quote = $this->createQuote(['discount_total' => '5']);
        $this->actingAs($this->user)->post(route('sales.store'), $this->salePayload($quote, 160))
            ->assertSessionDoesntHaveErrors();
        $sale = Sale::query()->firstOrFail();

        $this->patch(route('sales.cancel', $sale->id), ['reason' => 'Cliente desistiu'])
            ->assertRedirect(route('sales.index'));

        $quote->refresh();
        $this->assertSame('open', $quote->status);
        $this->assertNull($quote->converted_sale_id);
        $this->get(route('sales.create', ['quote_id' => $quote->id]))
            ->assertOk()
            ->assertSee('Convertendo o orçamento');
    }

    public function test_a_suspended_sale_from_a_quote_blocks_other_conversions_and_quote_changes(): void
    {
        $quote = $this->createQuote(['discount_total' => '5']);
        $payload = $this->salePayload($quote, 160);
        $payload['status'] = 'draft';
        unset($payload['payments']);

        $this->actingAs($this->user)->post(route('sales.store'), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('open', $quote->fresh()->status);
        $this->from(route('sales.create'))
            ->post(route('sales.store'), $payload)
            ->assertSessionHasErrors('sale_quote_id');
        $this->from(route('sales.quotes.show', $quote->id))
            ->put(route('sales.quotes.update', $quote->id), $this->quotePayload())
            ->assertSessionHasErrors('quote');
        $this->from(route('sales.quotes.show', $quote->id))
            ->patch(route('sales.quotes.cancel', $quote->id))
            ->assertSessionHasErrors('quote');
        $this->get(route('sales.quotes.show', $quote->id))
            ->assertOk()
            ->assertSee('Existe uma venda em andamento para este orçamento');
    }

    public function test_open_quote_can_be_edited_in_the_pdv_and_cancelled(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->user)
            ->get(route('sales.create', ['quote_id' => $quote->id, 'mode' => 'quote']))
            ->assertOk()
            ->assertSee('Editando o orçamento')
            ->assertSee('Salvar alterações do orçamento')
            ->assertSee('data-pdv-mode="quote"', false);

        $this->put(route('sales.quotes.update', $quote->id), [
            'tutor_id' => $this->tutor->id,
            'valid_until' => today()->addDays(15)->toDateString(),
            'items' => [[
                'type' => 'service',
                'petshop_service_id' => $this->service->id,
                'description' => 'Banho completo',
                'quantity' => '2',
                'unit_price' => '75,00',
            ]],
        ])->assertRedirect(route('sales.quotes.show', $quote->id));

        $quote->refresh()->load('items');
        $this->assertCount(1, $quote->items);
        $this->assertEquals(150, (float) $quote->total);
        $this->assertSame(today()->addDays(15)->toDateString(), $quote->valid_until->toDateString());

        $this->patch(route('sales.quotes.cancel', $quote->id), ['reason' => 'Preço alto'])
            ->assertRedirect(route('sales.quotes.show', $quote->id));
        $quote->refresh();
        $this->assertSame('cancelled', $quote->status);
        $this->assertSame('Preço alto', $quote->cancellation_reason);

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->salePayload($quote, 150))
            ->assertSessionHasErrors('sale_quote_id');
    }

    public function test_quotes_are_isolated_by_clinic(): void
    {
        $quote = $this->createQuote();
        $otherClinic = $this->clinic('Clinica Vizinha', '00000000000503');
        $otherUser = $this->userForClinic($otherClinic, ['sales.manage']);

        $this->actingAs($otherUser)->get(route('sales.quotes.index'))
            ->assertOk()
            ->assertDontSee($quote->code);
        $this->get(route('sales.quotes.show', $quote->id))->assertNotFound();
        $this->put(route('sales.quotes.update', $quote->id), $this->quotePayload())->assertNotFound();
        $this->patch(route('sales.quotes.cancel', $quote->id))->assertNotFound();
        $this->assertSame('open', $quote->fresh()->status);
    }

    public function test_quote_page_is_printable_and_links_to_whatsapp(): void
    {
        $quote = $this->createQuote(['notes' => 'Entrega combinada']);

        $this->actingAs($this->user)
            ->get(route('sales.quotes.show', $quote->id))
            ->assertOk()
            ->assertSee('ORÇAMENTO '.$quote->code)
            ->assertSee('Banho completo')
            ->assertSee('R$ 165,00')
            ->assertSee('Entrega combinada')
            ->assertSee('Documento sem valor fiscal')
            ->assertSee('https://wa.me/5521999998888?text=', false)
            ->assertSee('Converter em venda');
    }

    public function test_delivery_sale_adds_the_fee_to_the_total_and_to_the_receipt(): void
    {
        $base = [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'tutor_id' => $this->tutor->id,
            'sale_type' => 'delivery',
            'delivery_fee' => '12,00',
            'delivery_address' => 'Rua das Flores, 10 - Centro',
            'items' => [[
                'type' => 'product',
                'product_id' => $this->product->id,
                'description' => $this->product->name,
                'quantity' => '1',
                'unit_price' => '50',
            ]],
        ];

        $this->actingAs($this->user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $base + ['payments' => [['method' => 'pix', 'amount' => '50']]])
            ->assertSessionHasErrors('payments');

        $this->post(route('sales.store'), $base + ['payments' => [['method' => 'pix', 'amount' => '62']]])
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('delivery', $sale->sale_type);
        $this->assertEquals(12, (float) $sale->delivery_fee);
        $this->assertEquals(62, (float) $sale->total);
        $this->assertSame('Rua das Flores, 10 - Centro', $sale->delivery_address);
        $this->assertEquals(62, (float) FinancialTransaction::query()->findOrFail($sale->financial_transaction_id)->amount);

        $this->get(route('sales.receipt', $sale->id))
            ->assertOk()
            ->assertSee('Delivery ou atendimento domiciliar')
            ->assertSee('Taxa de entrega')
            ->assertSee('Rua das Flores, 10 - Centro');

        $summary = app(SaleProfitabilityService::class)->summary();
        $this->assertEquals(50, (float) $summary['stats']['net_revenue']);
    }

    public function test_in_store_sale_ignores_delivery_fee_and_address(): void
    {
        $this->actingAs($this->user)->post(route('sales.store'), [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'sale_type' => 'in_store',
            'delivery_fee' => '12,00',
            'delivery_address' => 'Não deve ficar',
            'items' => [[
                'type' => 'product',
                'product_id' => $this->product->id,
                'description' => $this->product->name,
                'quantity' => '1',
                'unit_price' => '50',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '50']],
        ])->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('in_store', $sale->sale_type);
        $this->assertEquals(0, (float) $sale->delivery_fee);
        $this->assertNull($sale->delivery_address);
        $this->assertEquals(50, (float) $sale->total);
    }

    public function test_sale_type_defaults_to_in_store_and_rejects_unknown_values(): void
    {
        $payload = [
            'pdv_checkout' => '1',
            'status' => 'draft',
            'items' => [[
                'type' => 'custom',
                'description' => 'Item avulso',
                'quantity' => '1',
                'unit_price' => '20',
            ]],
        ];

        $this->actingAs($this->user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $payload + ['sale_type' => 'drone'])
            ->assertSessionHasErrors('sale_type');

        $this->post(route('sales.store'), $payload)->assertSessionDoesntHaveErrors();
        $this->assertSame('in_store', Sale::query()->firstOrFail()->sale_type);
    }

    public function test_delivery_quote_keeps_the_fee_and_prefills_it_when_converted(): void
    {
        $quote = $this->createQuote([
            'sale_type' => 'delivery',
            'delivery_fee' => '10,00',
            'delivery_address' => 'Rua das Flores, 10',
        ]);

        $this->assertEquals(175, (float) $quote->total);

        $this->actingAs($this->user)
            ->get(route('sales.create', ['quote_id' => $quote->id]))
            ->assertOk()
            ->assertSee('value="delivery" selected', false)
            ->assertSee('value="10,00" data-pdv-delivery-fee', false)
            ->assertSee('Rua das Flores, 10');
    }

    public function test_pdv_renders_quote_mode_sale_types_and_tutor_address(): void
    {
        $this->actingAs($this->user)
            ->get(route('sales.create', ['mode' => 'quote']))
            ->assertOk()
            ->assertSee('data-pdv-mode="quote"', false)
            ->assertSee('Salvar orçamento')
            ->assertSee('Presencial, para consumidor final')
            ->assertSee('Pedido por telefone, envio por transportadora')
            ->assertSee('data-address="Rua das Flores, 10 - Centro - Niterói/RJ - CEP 24000-000"', false);

        $this->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-pdv-mode="sale"', false)
            ->assertSee('data-pdv-mode-button="quote"', false);
    }

    public function test_advanced_form_keeps_the_sale_type_of_a_draft(): void
    {
        $this->actingAs($this->user)->post(route('sales.store'), [
            'status' => 'draft',
            'sale_type' => 'phone_shipping',
            'delivery_fee' => '15',
            'delivery_address' => 'Av. Brasil, 500',
            'items' => [[
                'type' => 'custom',
                'description' => 'Antipulgas',
                'quantity' => '1',
                'unit_price' => '40',
            ]],
        ])->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->firstOrFail();
        $this->assertEquals(55, (float) $sale->total);

        $this->get(route('sales.edit', $sale->id))
            ->assertOk()
            ->assertSee('value="phone_shipping" selected', false);

        $this->put(route('sales.update', $sale->id), [
            'status' => 'draft',
            'sale_type' => 'phone_shipping',
            'delivery_fee' => '20',
            'delivery_address' => 'Av. Brasil, 500',
            'items' => [[
                'type' => 'custom',
                'description' => 'Antipulgas',
                'quantity' => '1',
                'unit_price' => '40',
            ]],
        ])->assertSessionDoesntHaveErrors();

        $sale->refresh();
        $this->assertSame('phone_shipping', $sale->sale_type);
        $this->assertEquals(60, (float) $sale->total);
    }

    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'tutor_id' => $this->tutor->id,
            'sale_type' => 'in_store',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $this->product->id,
                    'description' => $this->product->name,
                    'quantity' => '2',
                    'unit_price' => '45,00',
                    'discount_total' => '5,00',
                ],
                [
                    'type' => 'service',
                    'petshop_service_id' => $this->service->id,
                    'description' => 'Banho completo',
                    'quantity' => '1',
                    'unit_price' => '80,00',
                ],
            ],
        ], $overrides);
    }

    private function createQuote(array $overrides = []): SaleQuote
    {
        $this->actingAs($this->user)
            ->post(route('sales.quotes.store'), $this->quotePayload($overrides))
            ->assertSessionDoesntHaveErrors();

        // Drop the "Orçamento ORC-... salvo." flash so it cannot leak into
        // the next page assertions.
        $this->flushSession();

        return SaleQuote::query()->latest('id')->firstOrFail();
    }

    private function salePayload(SaleQuote $quote, float $payment): array
    {
        return [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'sale_quote_id' => $quote->id,
            'tutor_id' => $quote->tutor_id,
            'discount_total' => (string) $quote->discount_total,
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $this->product->id,
                    'description' => $this->product->name,
                    'quantity' => '2',
                    'unit_price' => '45',
                    'discount_total' => '5',
                ],
                [
                    'type' => 'service',
                    'petshop_service_id' => $this->service->id,
                    'description' => 'Banho completo',
                    'quantity' => '1',
                    'unit_price' => '80',
                ],
            ],
            'payments' => [['method' => 'pix', 'amount' => (string) $payment]],
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

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);

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

        return $user;
    }
}
