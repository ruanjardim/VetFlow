<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Services\PaymentMethodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\OpensCashSessions;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use OpensCashSessions;
    use RefreshDatabase;

    private Clinic $clinic;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->clinic('Clinica Maquininha', '00000000000601');
        $this->user = $this->userForClinic($this->clinic, ['sales.manage', 'payment-methods.manage']);
        $this->openCashSession($this->user);
        $this->product = Product::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Ração premium',
            'cost_price' => 40,
            'sale_price' => 100,
            'stock_quantity' => 50,
            'active' => true,
        ]);
    }

    public function test_the_pdv_creates_the_default_methods_once_per_clinic(): void
    {
        $this->actingAs($this->user)->get(route('sales.create'))->assertOk();
        $this->get(route('sales.create'))->assertOk();

        $methods = PaymentMethod::query()->where('clinic_id', $this->clinic->id)->ordered()->get();

        $this->assertSame(
            ['Dinheiro', 'Pix', 'Cartão de débito', 'Cartão de crédito', 'Transferência', 'Outro'],
            $methods->pluck('name')->all()
        );
        $this->assertSame(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'other'], $methods->pluck('kind')->all());
        $this->assertSame(12, $methods->firstWhere('kind', 'credit_card')->maxInstallments());
        $this->assertSame(1, $methods->firstWhere('kind', 'debit_card')->settlement_days);
    }

    public function test_manager_registers_a_card_machine_with_fees_and_settlement(): void
    {
        $response = $this->actingAs($this->user)->post(route('sales.payment-methods.store'), [
            'name' => 'Rede Visa Crédito',
            'kind' => 'credit_card',
            'acquirer' => 'Rede',
            'card_brand' => 'Visa',
            'fee_percent' => '3,19',
            'installment_fee_percent' => '4,99',
            'settlement_days' => '30',
            'max_installments' => '6',
            'installment_settlement' => 'monthly',
            'requires_reference' => '1',
            'active' => '1',
        ]);

        $response->assertRedirect(route('sales.payment-methods.index'));

        $method = PaymentMethod::query()->where('name', 'Rede Visa Crédito')->firstOrFail();
        $this->assertSame($this->clinic->id, $method->clinic_id);
        $this->assertEquals(3.19, (float) $method->fee_percent);
        $this->assertEquals(4.99, (float) $method->installment_fee_percent);
        $this->assertSame(30, $method->settlement_days);
        $this->assertSame(6, $method->max_installments);
        $this->assertSame('monthly', $method->installmentSettlement());
        $this->assertTrue($method->requires_reference);
        // Defaults are created first (10 to 60), so the new method goes last.
        $this->assertSame(70, $method->sort_order);
        $this->assertSame(7, PaymentMethod::query()->where('clinic_id', $this->clinic->id)->count());

        $this->get(route('sales.payment-methods.index'))
            ->assertOk()
            ->assertSee('Rede Visa Crédito')
            ->assertSee('3,19% à vista · 4,99% parcelado')
            ->assertSee('Até 6x');
    }

    public function test_non_credit_methods_drop_installments_and_card_fields(): void
    {
        $this->actingAs($this->user)->post(route('sales.payment-methods.store'), [
            'name' => 'Pix Itaú',
            'kind' => 'pix',
            'card_brand' => 'Visa',
            'fee_percent' => '0,99',
            'installment_fee_percent' => '2',
            'settlement_days' => '0',
            'max_installments' => '5',
            'requires_reference' => '0',
            'active' => '1',
        ])->assertSessionDoesntHaveErrors();

        $method = PaymentMethod::query()->where('name', 'Pix Itaú')->firstOrFail();
        $this->assertNull($method->card_brand);
        $this->assertNull($method->installment_fee_percent);
        $this->assertSame(1, $method->max_installments);
        $this->assertEquals(0.99, (float) $method->fee_percent);
    }

    public function test_method_names_are_unique_within_the_clinic(): void
    {
        $this->actingAs($this->user)->get(route('sales.payment-methods.index'))->assertOk();

        $this->from(route('sales.payment-methods.create'))
            ->post(route('sales.payment-methods.store'), $this->methodPayload(['name' => 'Dinheiro', 'kind' => 'cash']))
            ->assertSessionHasErrors('name');

        $dinheiro = PaymentMethod::query()->where('clinic_id', $this->clinic->id)->where('name', 'Dinheiro')->firstOrFail();

        $this->put(route('sales.payment-methods.update', $dinheiro->id), $this->methodPayload([
            'name' => 'Dinheiro',
            'kind' => 'cash',
            'settlement_days' => '0',
        ]))->assertSessionDoesntHaveErrors();
    }

    public function test_payment_methods_need_their_own_permission_and_stay_in_the_clinic(): void
    {
        $cashier = $this->userForClinic($this->clinic, ['sales.manage']);

        $this->actingAs($cashier)->get(route('sales.payment-methods.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('sales.receivables'))->assertForbidden();

        $otherClinic = $this->clinic('Outra Clinica', '00000000000602');
        app(PaymentMethodService::class)->ensureDefaults($otherClinic->id);
        $foreign = PaymentMethod::query()->withoutGlobalScopes()->where('clinic_id', $otherClinic->id)->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('sales.payment-methods.edit', $foreign->id))
            ->assertNotFound();

        $this->put(route('sales.payment-methods.update', $foreign->id), $this->methodPayload(['name' => 'Tomada']))
            ->assertNotFound();

        $this->assertSame('Dinheiro', $foreign->fresh()->name);
    }

    public function test_pdv_card_payment_keeps_fee_net_amount_and_settlement_date(): void
    {
        $method = $this->creditMachine();

        $this->actingAs($this->user)
            ->post(route('sales.store'), $this->pdvSale(300, [[
                'payment_method_id' => $method->id,
                'amount' => '300,00',
                'installments' => '3',
                'reference' => 'NSU 123456',
            ]]))
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $payment = $sale->payments()->firstOrFail();

        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('credit_card', $payment->method);
        $this->assertSame($method->id, $payment->payment_method_id);
        $this->assertSame('Rede', $payment->acquirer);
        $this->assertSame('Visa', $payment->card_brand);
        $this->assertSame(3, $payment->installments);
        $this->assertEquals(15.00, (float) $payment->fee_amount);
        $this->assertEquals(285.00, (float) $payment->net_amount);
        $this->assertSame(today()->addDays(30)->toDateString(), $payment->expected_settlement_date->toDateString());

        $this->get(route('sales.receipt', $sale->id))
            ->assertOk()
            ->assertSee('Rede Visa Crédito');
    }

    public function test_a_single_installment_uses_the_upfront_fee(): void
    {
        $method = $this->creditMachine();

        $this->actingAs($this->user)
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $method->id,
                'amount' => '100,00',
                'installments' => '1',
                'reference' => '998877',
            ]]))
            ->assertSessionDoesntHaveErrors();

        $payment = SalePayment::query()->latest('id')->firstOrFail();
        $this->assertEquals(3.00, (float) $payment->fee_amount);
        $this->assertEquals(97.00, (float) $payment->net_amount);
    }

    public function test_payment_informing_only_the_kind_uses_the_first_method_of_that_kind(): void
    {
        $this->actingAs($this->user)
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'method' => 'debit_card',
                'amount' => '100,00',
            ]]))
            ->assertSessionDoesntHaveErrors();

        $payment = SalePayment::query()->latest('id')->firstOrFail();
        $debit = PaymentMethod::query()->where('clinic_id', $this->clinic->id)->where('kind', 'debit_card')->firstOrFail();

        $this->assertSame($debit->id, $payment->payment_method_id);
        $this->assertSame('debit_card', $payment->method);
        $this->assertEquals(0, (float) $payment->fee_amount);
        $this->assertEquals(100, (float) $payment->net_amount);
        $this->assertSame(today()->addDay()->toDateString(), $payment->expected_settlement_date->toDateString());
    }

    public function test_pdv_rejects_foreign_inactive_and_over_installment_methods(): void
    {
        $method = $this->creditMachine();
        $otherClinic = $this->clinic('Outra Clinica', '00000000000603');
        app(PaymentMethodService::class)->ensureDefaults($otherClinic->id);
        $foreign = PaymentMethod::query()->withoutGlobalScopes()->where('clinic_id', $otherClinic->id)->where('kind', 'pix')->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $foreign->id,
                'amount' => '100,00',
            ]]))
            ->assertSessionHasErrors('payments.0.payment_method_id');

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $method->id,
                'amount' => '100,00',
                'installments' => '8',
                'reference' => '1',
            ]]))
            ->assertSessionHasErrors(['payments' => 'Rede Visa Crédito aceita até 6 parcelas.']);

        $debit = PaymentMethod::query()->where('clinic_id', $this->clinic->id)->where('kind', 'debit_card')->firstOrFail();

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $debit->id,
                'amount' => '100,00',
                'installments' => '2',
            ]]))
            ->assertSessionHasErrors(['payments' => 'Cartão de débito não aceita parcelamento.']);

        $method->update(['active' => false]);

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $method->id,
                'amount' => '100,00',
                'reference' => '1',
            ]]))
            ->assertSessionHasErrors(['payments' => 'A forma de pagamento Rede Visa Crédito está inativa. Escolha outra.']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_nsu_is_required_to_finish_but_not_to_suspend(): void
    {
        $method = $this->creditMachine();

        $this->actingAs($this->user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $method->id,
                'amount' => '100,00',
            ]]))
            ->assertSessionHasErrors(['payments' => 'Informe o NSU ou a autorização do pagamento em Rede Visa Crédito.']);

        $this->assertDatabaseCount('sales', 0);

        $draft = $this->pdvSale(100, [[
            'payment_method_id' => $method->id,
            'amount' => '100,00',
        ]]);
        $draft['status'] = 'draft';

        $this->post(route('sales.store'), $draft)->assertSessionDoesntHaveErrors();
        $this->assertSame('draft', Sale::query()->latest('id')->value('status'));
    }

    public function test_change_can_only_come_from_a_cash_method(): void
    {
        $cash = $this->defaults()->firstWhere('kind', 'cash');
        $pix = $this->defaults()->firstWhere('kind', 'pix');

        $this->actingAs($this->user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [[
                'payment_method_id' => $pix->id,
                'amount' => '150,00',
            ]]))
            ->assertSessionHasErrors(['payments' => 'O troco não pode exceder o valor recebido em dinheiro.']);

        $this->post(route('sales.store'), $this->pdvSale(100, [[
            'payment_method_id' => $cash->id,
            'amount' => '150,00',
        ]]))->assertSessionDoesntHaveErrors();

        $this->assertEquals(50, (float) Sale::query()->latest('id')->value('change_total'));
    }

    public function test_later_receipt_uses_the_clinic_method_and_its_rules(): void
    {
        $method = $this->creditMachine();
        $pix = $this->defaults()->firstWhere('kind', 'pix');

        $this->actingAs($this->user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $this->product->id,
                'description' => $this->product->name,
                'quantity' => '1',
                'unit_price' => '100',
            ]],
            'payments' => [['payment_method_id' => $pix->id, 'amount' => '40,00']],
        ])->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('partial', $sale->payment_status);

        $this->from(route('sales.edit', $sale->id))
            ->post(route('sales.payments.store', $sale->id), [
                'payment_method_id' => $method->id,
                'amount' => '60,00',
                'installments' => '2',
            ])
            ->assertSessionHasErrors('payment_method_id');

        $this->post(route('sales.payments.store', $sale->id), [
            'payment_method_id' => $method->id,
            'amount' => '60,00',
            'installments' => '2',
            'reference' => '445566',
        ])->assertSessionDoesntHaveErrors();

        $payment = $sale->payments()->latest('id')->firstOrFail();
        $this->assertSame('credit_card', $payment->method);
        $this->assertSame(2, $payment->installments);
        $this->assertEquals(3.00, (float) $payment->fee_amount);
        $this->assertEquals(57.00, (float) $payment->net_amount);
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_cashier_groups_receipts_by_method_with_fees(): void
    {
        $method = $this->creditMachine();
        $cash = $this->defaults()->firstWhere('kind', 'cash');

        $this->actingAs($this->user)->post(route('sales.store'), $this->pdvSale(200, [[
            'payment_method_id' => $method->id,
            'amount' => '200,00',
            'installments' => '2',
            'reference' => '1',
        ]]))->assertSessionDoesntHaveErrors();
        $this->post(route('sales.store'), $this->pdvSale(100, [[
            'payment_method_id' => $cash->id,
            'amount' => '100,00',
        ]]))->assertSessionDoesntHaveErrors();

        $this->get(route('sales.cashier'))
            ->assertOk()
            ->assertSee('Rede Visa Crédito')
            ->assertSee('Cartão de crédito')
            ->assertSee('Taxas de cartão')
            ->assertSee('R$ 10,00')
            ->assertSee('R$ 190,00');
    }

    public function test_receivables_list_one_row_per_installment_on_the_expected_date(): void
    {
        $monthly = $this->creditMachine();
        $upfront = PaymentMethod::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Stone Crédito Antecipado',
            'kind' => 'credit_card',
            'acquirer' => 'Stone',
            'fee_percent' => 4,
            'settlement_days' => 1,
            'max_installments' => 3,
            'active' => true,
            'metadata' => ['installment_settlement' => 'upfront'],
        ]);

        $this->actingAs($this->user)->post(route('sales.store'), $this->pdvSale(300, [[
            'payment_method_id' => $monthly->id,
            'amount' => '300,00',
            'installments' => '3',
            'reference' => 'A1',
        ]]))->assertSessionDoesntHaveErrors();
        $this->post(route('sales.store'), $this->pdvSale(100, [[
            'payment_method_id' => $upfront->id,
            'amount' => '100,00',
            'installments' => '2',
        ]]))->assertSessionDoesntHaveErrors();
        $cancelled = $this->post(route('sales.store'), $this->pdvSale(100, [[
            'payment_method_id' => $monthly->id,
            'amount' => '100,00',
            'reference' => 'C1',
        ]]));
        $cancelled->assertSessionDoesntHaveErrors();
        $this->patch(route('sales.cancel', Sale::query()->latest('id')->value('id')), ['reason' => 'Teste']);

        $summary = app(PaymentMethodService::class)->receivables(
            today()->toDateString(),
            today()->addDays(100)->toDateString()
        );

        $rows = $summary['rows']->map(fn (array $row) => [
            $row['method_label'],
            $row['number'].'/'.$row['installments'],
            $row['date']->toDateString(),
            $row['gross'],
            $row['fee'],
            $row['net'],
        ])->all();

        $this->assertEquals([
            ['Stone Crédito Antecipado', '1/2', today()->addDay()->toDateString(), 50.0, 2.0, 48.0],
            ['Stone Crédito Antecipado', '2/2', today()->addDay()->toDateString(), 50.0, 2.0, 48.0],
            ['Rede Visa Crédito', '1/3', today()->addDays(30)->toDateString(), 100.0, 5.0, 95.0],
            ['Rede Visa Crédito', '2/3', today()->addDays(60)->toDateString(), 100.0, 5.0, 95.0],
            ['Rede Visa Crédito', '3/3', today()->addDays(90)->toDateString(), 100.0, 5.0, 95.0],
        ], $rows);
        $this->assertEquals(400.0, $summary['stats']['gross']);
        $this->assertEquals(19.0, $summary['stats']['fee']);
        $this->assertEquals(381.0, $summary['stats']['net']);

        $this->get(route('sales.receivables', [
            'from' => today()->addDays(40)->toDateString(),
            'to' => today()->addDays(70)->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('2/3')
            ->assertDontSee('1/3')
            ->assertSee('R$ 95,00');
    }

    public function test_global_operator_uses_the_methods_of_the_selected_clinic(): void
    {
        $otherClinic = $this->clinic('Outra Clinica', '00000000000604');
        $global = $this->userForClinic(null, ['sales.manage', 'payment-methods.manage']);
        $this->openCashSession($global, $this->clinic);
        $service = app(PaymentMethodService::class);
        $pix = $service->forClinic($this->clinic->id)->firstWhere('kind', 'pix');
        $foreignPix = $service->forClinic($otherClinic->id)->firstWhere('kind', 'pix');

        $payload = $this->pdvSale(100, [['payment_method_id' => $foreignPix->id, 'amount' => '100,00']]);
        $payload['clinic_id'] = $this->clinic->id;

        $this->actingAs($global)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $payload)
            ->assertSessionHasErrors('payments.0.payment_method_id');

        $payload['payments'][0]['payment_method_id'] = $pix->id;
        $this->post(route('sales.store'), $payload)->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame($this->clinic->id, $sale->clinic_id);
        $this->assertSame($pix->id, $sale->payments()->value('payment_method_id'));

        $this->post(route('sales.payment-methods.store'), $this->methodPayload(['clinic_id' => $otherClinic->id]))
            ->assertRedirect(route('sales.payment-methods.index', ['clinic_id' => $otherClinic->id]));

        $this->assertSame($otherClinic->id, PaymentMethod::query()->where('name', 'PagSeguro Débito')->value('clinic_id'));

        $this->get(route('sales.payment-methods.index', ['clinic_id' => $otherClinic->id]))
            ->assertOk()
            ->assertSee('PagSeguro Débito');
        $this->get(route('sales.payment-methods.index', ['clinic_id' => $this->clinic->id]))
            ->assertOk()
            ->assertDontSee('PagSeguro Débito');
    }

    public function test_installment_cents_that_do_not_divide_go_to_the_last_installment(): void
    {
        $method = $this->creditMachine();
        $payment = new SalePayment([
            'method' => 'credit_card',
            'payment_method_id' => $method->id,
            'amount' => 100,
            'fee_amount' => 5,
            'installments' => 3,
            'paid_at' => now(),
            'expected_settlement_date' => today()->addDays(30),
        ]);

        $rows = app(PaymentMethodService::class)->installmentSchedule($payment);

        $this->assertSame([33.33, 33.33, 33.34], array_column($rows, 'gross'));
        $this->assertSame([1.66, 1.66, 1.68], array_column($rows, 'fee'));
        $this->assertEquals(100, array_sum(array_column($rows, 'gross')));
    }

    private function creditMachine(): PaymentMethod
    {
        $this->defaults();

        return PaymentMethod::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rede Visa Crédito',
            'kind' => 'credit_card',
            'acquirer' => 'Rede',
            'card_brand' => 'Visa',
            'fee_percent' => 3,
            'installment_fee_percent' => 5,
            'settlement_days' => 30,
            'max_installments' => 6,
            'requires_reference' => true,
            'active' => true,
            'sort_order' => 5,
        ]);
    }

    private function defaults()
    {
        return app(PaymentMethodService::class)->forClinic($this->clinic->id);
    }

    private function pdvSale(float $unitPrice, array $payments): array
    {
        return [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $this->product->id,
                'description' => $this->product->name,
                'quantity' => '1',
                'unit_price' => (string) $unitPrice,
            ]],
            'payments' => $payments,
        ];
    }

    private function methodPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'PagSeguro Débito',
            'kind' => 'debit_card',
            'acquirer' => 'PagSeguro',
            'fee_percent' => '1,99',
            'settlement_days' => '1',
            'max_installments' => '1',
            'requires_reference' => '0',
            'active' => '1',
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

    private function userForClinic(?Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
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
