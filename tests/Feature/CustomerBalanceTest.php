<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\CashSession;
use App\Modules\Sales\Models\CashSessionMovement;
use App\Modules\Sales\Models\CustomerCreditEntry;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\CashSessionService;
use App\Modules\Sales\Services\CustomerBalanceService;
use App\Modules\Sales\Services\PaymentMethodService;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OpensCashSessions;
use Tests\TestCase;

class CustomerBalanceTest extends TestCase
{
    use OpensCashSessions;
    use RefreshDatabase;

    private Clinic $clinic;

    private User $operator;

    private Tutor $tutor;

    private Product $product;

    private CashSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-24 10:00:00'));

        $this->clinic = Clinic::query()->create([
            'corporate_name' => 'Clinica Saldo',
            'trade_name' => 'Clinica Saldo',
            'cnpj' => '00000000000801',
            'active' => true,
        ]);
        $this->operator = $this->userFor($this->clinic, ['sales.manage', 'tutors.manage']);
        $this->session = $this->openCashSession($this->operator, $this->clinic, 100);
        $this->tutor = Tutor::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rackel Martins',
            'phone' => '21976800110',
            'active' => true,
        ]);
        $this->product = Product::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Ração premium',
            'cost_price' => 10,
            'sale_price' => 50,
            'stock_quantity' => 100,
            'active' => true,
        ]);
    }

    public function test_pdv_sells_to_be_paid_later_only_to_an_identified_customer(): void
    {
        $this->actingAs($this->operator)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(100, [['payment_method_id' => $this->method('pix')->id, 'amount' => '30,00']], ['pay_later' => '1']))
            ->assertSessionHasErrors('tutor_id');

        $this->post(route('sales.store'), $this->pdvSale(100, [['payment_method_id' => $this->method('pix')->id, 'amount' => '30,00']], [
            'pay_later' => '1',
            'tutor_id' => $this->tutor->id,
        ]))->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $sale->status);
        $this->assertSame('partial', $sale->payment_status);

        // Without receiving anything, no cash session is needed.
        $colleague = $this->userFor($this->clinic, ['sales.manage']);
        $this->actingAs($colleague)
            ->post(route('sales.store'), $this->pdvSale(50, [], ['pay_later' => '1', 'tutor_id' => $this->tutor->id]))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('pending', Sale::query()->latest('id')->value('payment_status'));

        $this->assertEquals(
            ['debt' => 120.0, 'credit' => 0.0, 'open_sales' => 2],
            app(CustomerBalanceService::class)->summary($this->tutor->id)
        );

        $this->actingAs($this->operator)
            ->getJson(route('sales.customer-balances.summary', $this->tutor->id))
            ->assertOk()
            ->assertJsonPath('debt', 120)
            ->assertJsonPath('open_sales', 2);

        $this->get(route('sales.customer-balances.index', ['filter' => 'debt']))
            ->assertOk()
            ->assertSee('Rackel Martins')
            ->assertSee('R$ 120,00');

        $this->get(route('tutores.edit', $this->tutor->id))
            ->assertOk()
            ->assertSee('deve R$ 120,00 em 2 vendas', false);
    }

    public function test_receiving_several_open_sales_at_once_pays_the_oldest_first(): void
    {
        $this->actingAs($this->operator);
        $first = $this->fiadoSale(70);
        $this->travel(1)->hours();
        $second = $this->fiadoSale(50);

        $this->post(route('sales.customer-balances.settle', $this->tutor->id), [
            'amount' => '100,00',
            'payment_method_id' => $this->method('cash')->id,
        ])->assertRedirect(route('sales.customer-balances.show', $this->tutor->id));

        $this->assertSame('paid', $first->fresh()->payment_status);
        $this->assertSame('partial', $second->fresh()->payment_status);
        $this->assertEquals(30, (float) $second->fresh()->paid_total);
        $this->assertSame($this->session->id, $second->payments()->value('cash_session_id'));
        $this->assertEquals(20, app(CustomerBalanceService::class)->summary($this->tutor->id)['debt']);

        $this->from(route('sales.customer-balances.show', $this->tutor->id))
            ->post(route('sales.customer-balances.settle', $this->tutor->id), [
                'amount' => '50,00',
                'payment_method_id' => $this->method('cash')->id,
            ])
            ->assertSessionHasErrors('amount');

        $this->from(route('sales.customer-balances.show', $this->tutor->id))
            ->post(route('sales.customer-balances.settle', $this->tutor->id), [
                'amount' => '20,00',
                'payment_method_id' => 'customer_credit',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_advance_becomes_credit_and_revenue_only_when_used(): void
    {
        $this->actingAs($this->operator)
            ->post(route('sales.customer-balances.deposit', $this->tutor->id), [
                'amount' => '80,00',
                'payment_method_id' => $this->method('pix')->id,
                'description' => 'Ração do mês',
            ])->assertSessionDoesntHaveErrors();

        $this->assertEquals(80, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));
        $this->assertSame(0, FinancialTransaction::query()->where('type', 'income')->count());
        $deposit = CashSessionMovement::query()->where('type', 'credit_deposit')->sole();
        $this->assertSame('pix', $deposit->method);
        $this->assertEquals(80, (float) $deposit->amount);

        $this->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => 'customer_credit', 'method' => 'customer_credit', 'amount' => '50,00']], [
            'tutor_id' => $this->tutor->id,
        ]))->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $payment = $sale->payments()->sole();
        $this->assertSame('customer_credit', $payment->method);
        $this->assertNull($payment->cash_session_id);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(50, (float) FinancialTransaction::query()->where('type', 'income')->sole()->amount);
        $this->assertEquals(30, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));

        $summary = app(CashSessionService::class)->summary($this->session->fresh());
        $this->assertEquals(80, $summary['methods']->firstWhere('kind', 'pix')['received']);
        $this->assertEquals(100, $summary['cash']['expected']);

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => 'customer_credit', 'method' => 'customer_credit', 'amount' => '50,00']], [
                'tutor_id' => $this->tutor->id,
            ]))
            ->assertSessionHasErrors(['payments' => 'O cliente tem R$ 30,00 de crédito disponível.']);

        $this->get(route('sales.customer-balances.show', $this->tutor->id))
            ->assertOk()
            ->assertSee('Adiantamento')
            ->assertSee('Usado em venda')
            ->assertSee('R$ 30,00');
    }

    public function test_a_negative_credit_entry_cannot_overdraw_the_customer_balance(): void
    {
        $this->actingAs($this->operator);
        $balances = app(CustomerBalanceService::class);
        $balances->deposit($this->tutor, 60, $this->method('cash')->id, null, $this->operator);

        try {
            $balances->addEntry(
                $this->tutor->id,
                $this->clinic->id,
                'sale_payment',
                -61,
                ['description' => 'Tentativa de saldo negativo']
            );
            $this->fail('O lançamento negativo acima do saldo deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payments', $exception->errors());
        }

        $this->assertEquals(60, $balances->creditBalance($this->tutor->id));
        $this->assertSame(1, CustomerCreditEntry::query()->count());
    }

    public function test_change_can_be_kept_as_credit_for_an_identified_customer(): void
    {
        $this->actingAs($this->operator)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(80, [['payment_method_id' => $this->method('pix')->id, 'amount' => '100,00']], ['change_as_credit' => '1']))
            ->assertSessionHasErrors('tutor_id');

        // Paid 100 by Pix for 80: the 20 of change stay as credit.
        $this->post(route('sales.store'), $this->pdvSale(80, [['payment_method_id' => $this->method('pix')->id, 'amount' => '100,00']], [
            'change_as_credit' => '1',
            'tutor_id' => $this->tutor->id,
        ]))->assertSessionDoesntHaveErrors();

        $entry = CustomerCreditEntry::query()->sole();
        $this->assertSame('change', $entry->type);
        $this->assertEquals(20, (float) $entry->amount);

        $summary = app(CashSessionService::class)->summary($this->session->fresh());
        // Change of 20 considered given and deposited back as credit.
        $this->assertEquals(100, $summary['cash']['expected']);
        $this->assertEquals(100, $summary['methods']->firstWhere('kind', 'pix')['received']);
    }

    public function test_return_as_credit_and_cancellation_give_the_credit_back(): void
    {
        $this->actingAs($this->operator);
        app(CustomerBalanceService::class)->deposit($this->tutor, 60, $this->method('cash')->id, null, $this->operator);

        // 100 = 60 credit + 40 cash.
        $this->post(route('sales.store'), $this->pdvSale(50, [
            ['payment_method_id' => 'customer_credit', 'method' => 'customer_credit', 'amount' => '60,00'],
            ['payment_method_id' => $this->method('cash')->id, 'amount' => '40,00'],
        ], ['tutor_id' => $this->tutor->id, 'quantity' => '2']))->assertSessionDoesntHaveErrors();
        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertEquals(0, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));

        $this->patch(route('sales.cancel', $sale->id))->assertSessionDoesntHaveErrors();

        $this->assertEquals(60, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));
        $this->assertSame('cancellation', CustomerCreditEntry::query()->latest('id')->value('type'));
        $this->assertEquals(40, (float) CashSessionMovement::query()->where('type', 'refund')->sole()->amount);

        // A return refunded as credit moves no money.
        $this->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']], [
            'tutor_id' => $this->tutor->id,
        ]))->assertSessionDoesntHaveErrors();
        $returned = Sale::query()->with('items')->latest('id')->firstOrFail();
        $this->post(route('sales.returns.store', $returned->id), [
            'refund_method' => 'customer_credit',
            'refund_amount' => '50,00',
            'items' => [$returned->items->first()->id => ['quantity' => '1']],
        ])->assertSessionDoesntHaveErrors();

        $this->assertEquals(110, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));
        $this->assertSame(1, CashSessionMovement::query()->where('type', 'refund')->count());
        $this->assertSame(
            'customer_credit',
            FinancialTransaction::query()->where('description', 'Estorno venda '.$returned->code)->value('payment_method')
        );

        // Without a customer on the sale, a credit refund is refused.
        $this->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']]))
            ->assertSessionDoesntHaveErrors();
        $anonymous = Sale::query()->with('items')->latest('id')->firstOrFail();
        $this->from(route('sales.returns.create', $anonymous->id))
            ->post(route('sales.returns.store', $anonymous->id), [
                'refund_method' => 'customer_credit',
                'refund_amount' => '50,00',
                'items' => [$anonymous->items->first()->id => ['quantity' => '1']],
            ])
            ->assertSessionHasErrors('refund_method');
    }

    public function test_credit_can_be_given_back_and_pays_a_later_receipt(): void
    {
        $this->actingAs($this->operator);
        app(CustomerBalanceService::class)->deposit($this->tutor, 80, $this->method('cash')->id, null, $this->operator);

        $this->post(route('sales.customer-balances.refund', $this->tutor->id), [
            'amount' => '30,00',
            'payment_method_id' => $this->method('cash')->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertEquals(50, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));
        // 100 float + 80 advance - 30 given back.
        $this->assertEquals(150, app(CashSessionService::class)->summary($this->session->fresh())['cash']['expected']);

        $this->from(route('sales.customer-balances.show', $this->tutor->id))
            ->post(route('sales.customer-balances.refund', $this->tutor->id), [
                'amount' => '60,00',
                'payment_method_id' => $this->method('cash')->id,
            ])
            ->assertSessionHasErrors('amount');

        // A later receipt with credit needs no cash session.
        $sale = $this->fiadoSale(50);
        $colleague = $this->userFor($this->clinic, ['sales.manage']);

        $this->actingAs($colleague)
            ->post(route('sales.payments.store', $sale->id), [
                'payment_method_id' => 'customer_credit',
                'amount' => '50,00',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertEquals(0, app(CustomerBalanceService::class)->creditBalance($this->tutor->id));
    }

    public function test_balances_stay_inside_the_clinic(): void
    {
        $otherClinic = Clinic::query()->create([
            'corporate_name' => 'Outra',
            'trade_name' => 'Outra',
            'cnpj' => '00000000000802',
            'active' => true,
        ]);
        $foreignTutor = Tutor::query()->withoutGlobalScopes()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Cliente de fora',
            'phone' => '21999990000',
            'active' => true,
        ]);

        $this->actingAs($this->operator)->get(route('sales.customer-balances.show', $foreignTutor->id))->assertNotFound();
        $this->getJson(route('sales.customer-balances.summary', $foreignTutor->id))->assertNotFound();
        $this->post(route('sales.customer-balances.deposit', $foreignTutor->id), [
            'amount' => '10',
            'payment_method_id' => $this->method('cash')->id,
        ])->assertNotFound();

        $this->assertSame(0, CustomerCreditEntry::query()->withoutGlobalScopes()->count());
    }

    private function fiadoSale(float $unitPrice): Sale
    {
        $this->post(route('sales.store'), $this->pdvSale($unitPrice, [], [
            'pay_later' => '1',
            'tutor_id' => $this->tutor->id,
        ]))->assertSessionDoesntHaveErrors();

        return Sale::query()->latest('id')->firstOrFail();
    }

    private function method(string $kind): PaymentMethod
    {
        return app(PaymentMethodService::class)->forClinic($this->clinic->id)->firstWhere('kind', $kind);
    }

    private function pdvSale(float $unitPrice, array $payments, array $overrides = []): array
    {
        $quantity = $overrides['quantity'] ?? '1';
        unset($overrides['quantity']);

        return array_merge([
            'pdv_checkout' => '1',
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $this->product->id,
                'description' => $this->product->name,
                'quantity' => $quantity,
                'unit_price' => (string) $unitPrice,
            ]],
            'payments' => $payments,
        ], $overrides);
    }

    private function userFor(Clinic $clinic, array $permissionSlugs): User
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
