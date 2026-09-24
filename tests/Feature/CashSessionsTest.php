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
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Services\CashSessionService;
use App\Modules\Sales\Services\PaymentMethodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\OpensCashSessions;
use Tests\TestCase;

class CashSessionsTest extends TestCase
{
    use OpensCashSessions;
    use RefreshDatabase;

    private Clinic $clinic;

    private User $operator;

    private User $manager;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-24 09:00:00'));

        $this->clinic = $this->clinic('Clinica Caixa', '00000000000701');
        $this->operator = $this->userFor($this->clinic, ['sales.manage'], 'Ana Caixa');
        $this->manager = $this->userFor($this->clinic, ['sales.manage', 'cash-sessions.review'], 'Gestor');
        $this->product = Product::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Ração premium',
            'cost_price' => 10,
            'sale_price' => 50,
            'stock_quantity' => 100,
            'active' => true,
        ]);
    }

    public function test_receiving_requires_an_open_cash_session_but_suspending_does_not(): void
    {
        $this->actingAs($this->operator)
            ->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']]))
            ->assertSessionHasErrors('cash_session');

        $this->assertDatabaseCount('sales', 0);

        $draft = $this->pdvSale(50, []);
        $draft['status'] = 'draft';
        $this->post(route('sales.store'), $draft)->assertSessionDoesntHaveErrors();

        // A sale finished without receiving anything (to be paid later) does
        // not need the cash either.
        $this->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [$this->item(50)],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(['draft', 'completed'], Sale::query()->orderBy('id')->pluck('status')->all());
    }

    public function test_operator_opens_the_cash_from_the_pdv_once(): void
    {
        $this->actingAs($this->operator)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-pdv-cash-dialog', false);

        $response = $this->postJson(route('sales.cash-sessions.store'), ['opening_amount' => '150,00']);

        $response->assertCreated()
            ->assertJsonPath('session.code', 'CX-000001')
            ->assertJsonPath('session.opening_amount', 150);

        $session = CashSession::query()->sole();
        $this->assertSame($this->operator->id, $session->user_id);
        $this->assertSame($this->clinic->id, $session->clinic_id);
        $this->assertSame('open', $session->status);

        $this->postJson(route('sales.cash-sessions.store'), ['opening_amount' => '10'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('opening_amount');

        $this->assertSame(1, CashSession::query()->count());
    }

    public function test_session_summary_counts_cash_methods_movements_and_fees(): void
    {
        $session = $this->openCashSession($this->operator, $this->clinic, 100);
        $rede = $this->creditMachine();

        $this->actingAs($this->operator);
        // Cash sale of 50 paid with 100: 50 of change.
        $this->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('cash')->id, 'amount' => '100,00']]))
            ->assertSessionDoesntHaveErrors();
        $this->post(route('sales.store'), $this->pdvSale(30, [['payment_method_id' => $this->method('pix')->id, 'amount' => '30,00']]))
            ->assertSessionDoesntHaveErrors();
        $this->post(route('sales.store'), $this->pdvSale(200, [['payment_method_id' => $rede->id, 'amount' => '200,00', 'installments' => '2']]))
            ->assertSessionDoesntHaveErrors();

        $this->post(route('sales.cash-sessions.movements.store', $session->id), ['type' => 'supply', 'amount' => '20,00', 'description' => 'Troco do cofre'])
            ->assertSessionDoesntHaveErrors();
        $this->post(route('sales.cash-sessions.movements.store', $session->id), ['type' => 'withdrawal', 'amount' => '40,00', 'description' => 'Pagamento de comissão'])
            ->assertSessionDoesntHaveErrors();
        $this->post(route('sales.cash-sessions.movements.store', $session->id), ['type' => 'expense', 'amount' => '10,00', 'description' => 'Lanche', 'category' => 'Copa'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(3, SalePayment::query()->where('cash_session_id', $session->id)->count());
        $this->assertSame(3, Sale::query()->where('cash_session_id', $session->id)->count());

        $expense = FinancialTransaction::query()->where('reference', 'CX-000001')->sole();
        $this->assertSame('expense', $expense->type);
        $this->assertSame('paid', $expense->status);
        $this->assertEquals(10, (float) $expense->amount);

        $summary = app(CashSessionService::class)->summary($session->fresh());

        // 100 float + 100 cash - 50 change + 20 supply - 40 withdrawal - 10 expense.
        $this->assertEquals(120, $summary['cash']['expected']);
        $this->assertEquals(
            ['Pix' => 30.0, 'Rede Visa Crédito' => 200.0],
            $summary['methods']->mapWithKeys(fn (array $row) => [$row['label'] => $row['expected']])->all()
        );
        $this->assertEquals(10, $summary['totals']['fees']);
        $this->assertSame([['label' => 'Rede', 'amount' => 10.0, 'count' => 1]], $summary['fees_by_machine']);

        $this->get(route('sales.cash-sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Pagamento de comissão')
            ->assertSee('R$ 120,00')
            ->assertSee('Rede Visa Crédito');

        $this->from(route('sales.cash-sessions.show', $session->id))
            ->post(route('sales.cash-sessions.movements.store', $session->id), ['type' => 'withdrawal', 'amount' => '500,00', 'description' => 'Banco'])
            ->assertSessionHasErrors('amount');
    }

    public function test_operator_closes_with_counted_values_and_fees_become_one_expense_per_machine(): void
    {
        $session = $this->openCashSession($this->operator, $this->clinic, 100);
        $rede = $this->creditMachine();
        $this->actingAs($this->operator);
        $this->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('cash')->id, 'amount' => '50,00']]));
        $this->post(route('sales.store'), $this->pdvSale(200, [['payment_method_id' => $rede->id, 'amount' => '200,00', 'installments' => '2']]));
        $this->post(route('sales.store'), $this->pdvSale(30, [['payment_method_id' => $this->method('pix')->id, 'amount' => '30,00']]));

        $this->get(route('sales.cash-sessions.close', $session->id))
            ->assertOk()
            ->assertSee('Dinheiro contado na gaveta');

        $pixKey = 'payment_method_'.$this->method('pix')->id;
        $redeKey = 'payment_method_'.$rede->id;

        $this->post(route('sales.cash-sessions.close.store', $session->id), [
            'counted_cash' => '145,00',
            'counted_methods' => [$pixKey => '30,00', $redeKey => '200,00'],
            'cash_left' => '100,00',
            'notes' => 'Faltaram 5 reais',
        ])->assertRedirect(route('sales.cash-sessions.show', $session->id));

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertSame($this->operator->id, $session->closed_by);
        $this->assertEquals(150, (float) $session->expected_cash);
        $this->assertEquals(145, (float) $session->counted_cash);
        $this->assertEquals(380, (float) $session->expected_total);
        $this->assertEquals(375, (float) $session->counted_total);
        $this->assertEquals(-5, (float) $session->difference_total);
        $this->assertEquals(100, (float) $session->cash_left);
        $this->assertEquals(45, $session->closing_snapshot['cash']['withdrawn']);

        $fee = FinancialTransaction::query()->where('description', 'like', 'Taxas de cartão%')->sole();
        $this->assertSame('Taxas de cartão — Rede (caixa CX-000001)', $fee->description);
        $this->assertEquals(10, (float) $fee->amount);
        $this->assertSame('paid', $fee->status);
        $this->assertSame($fee->id, $session->closing_snapshot['fees_by_machine'][0]['financial_transaction_id']);

        // A closed session takes no more movements and the next opening
        // suggests what was left in the drawer.
        $this->post(route('sales.cash-sessions.movements.store', $session->id), ['type' => 'supply', 'amount' => '1', 'description' => 'x'])
            ->assertNotFound();
        $this->assertEquals(100, app(CashSessionService::class)->suggestedOpening($this->operator, $this->clinic->id));

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']]))
            ->assertSessionHasErrors('cash_session');
    }

    public function test_manager_reviews_or_reopens_a_closed_session(): void
    {
        $session = $this->openCashSession($this->operator, $this->clinic, 0);
        $rede = $this->creditMachine();
        $this->actingAs($this->operator);
        $this->post(route('sales.store'), $this->pdvSale(100, [['payment_method_id' => $rede->id, 'amount' => '100,00']]));
        $this->post(route('sales.cash-sessions.close.store', $session->id), ['counted_cash' => '0', 'counted_methods' => []]);

        $this->post(route('sales.cash-sessions.review', $session->id))->assertForbidden();
        $this->post(route('sales.cash-sessions.reopen', $session->id))->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('sales.cash-sessions.index'))
            ->assertOk()
            ->assertSee('CX-000001')
            ->assertSee('Ana Caixa')
            ->assertSee('1 caixa fechado aguarda conferência');

        $this->post(route('sales.cash-sessions.reopen', $session->id))->assertRedirect();
        $session->refresh();
        $this->assertSame('open', $session->status);
        $this->assertSame('cancelled', FinancialTransaction::query()->where('description', 'like', 'Taxas de cartão%')->sole()->status);

        $this->actingAs($this->operator)
            ->post(route('sales.cash-sessions.close.store', $session->id), ['counted_cash' => '0'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(1, FinancialTransaction::query()->where('description', 'like', 'Taxas de cartão%')->where('status', 'paid')->count());

        $this->actingAs($this->manager)
            ->post(route('sales.cash-sessions.review', $session->id), ['review_notes' => 'Conferido com o relatório da Rede'])
            ->assertRedirect(route('sales.cash-sessions.show', $session->id));

        $session->refresh();
        $this->assertSame('reviewed', $session->status);
        $this->assertSame($this->manager->id, $session->reviewed_by);
        $this->assertSame('Conferido com o relatório da Rede', $session->review_notes);

        $this->post(route('sales.cash-sessions.reopen', $session->id))->assertSessionHasErrors('cash_session');
    }

    public function test_session_left_open_is_closed_automatically_the_next_day(): void
    {
        $session = $this->openCashSession($this->operator, $this->clinic, 80);
        $this->actingAs($this->operator)
            ->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('cash')->id, 'amount' => '50,00']]))
            ->assertSessionDoesntHaveErrors();

        $this->travelTo(Carbon::parse('2026-09-25 08:30:00'));

        $this->get(route('sales.create'))->assertOk();

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertTrue($session->wasAutoClosed());
        $this->assertSame('2026-09-24 23:59:00', $session->closed_at->format('Y-m-d H:i:s'));
        $this->assertNull($session->closed_by);
        $this->assertEquals(130, (float) $session->counted_cash);
        $this->assertEquals(0, (float) $session->difference_total);
        $this->assertEquals(130, (float) $session->cash_left);

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']]))
            ->assertSessionHasErrors('cash_session');

        $this->assertEquals(130, app(CashSessionService::class)->suggestedOpening($this->operator, $this->clinic->id));

        $this->actingAs($this->manager)
            ->get(route('sales.cash-sessions.show', $session->id))
            ->assertOk()
            ->assertSee('fechado automaticamente');
    }

    public function test_later_receipts_returns_and_cancellations_move_the_operator_session(): void
    {
        $first = $this->openCashSession($this->operator, $this->clinic, 0);
        $this->actingAs($this->operator);

        // Sale received in cash and closed with the first session.
        $this->post(route('sales.store'), $this->pdvSale(60, [['payment_method_id' => $this->method('cash')->id, 'amount' => '60,00']]))
            ->assertSessionDoesntHaveErrors();
        $oldSale = Sale::query()->latest('id')->firstOrFail();
        // Sale to be paid later.
        $this->post(route('sales.store'), ['status' => 'completed', 'items' => [$this->item(40)]])->assertSessionDoesntHaveErrors();
        $pendingSale = Sale::query()->latest('id')->firstOrFail();
        $this->post(route('sales.cash-sessions.close.store', $first->id), ['counted_cash' => '60'])->assertSessionDoesntHaveErrors();

        // Without an open session the later receipt is refused.
        $this->from(route('sales.edit', $pendingSale->id))
            ->post(route('sales.payments.store', $pendingSale->id), ['payment_method_id' => $this->method('cash')->id, 'amount' => '40,00'])
            ->assertSessionHasErrors('payment_method_id');

        $second = $this->openCashSession($this->operator, $this->clinic, 0);
        $this->post(route('sales.payments.store', $pendingSale->id), ['payment_method_id' => $this->method('cash')->id, 'amount' => '40,00'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame($second->id, $pendingSale->payments()->latest('id')->value('cash_session_id'));

        // Cancelling the sale of the closed session gives the money back from
        // the current one.
        $this->patch(route('sales.cancel', $oldSale->id), ['reason' => 'Cliente desistiu'])->assertSessionDoesntHaveErrors();

        $refund = CashSessionMovement::query()->where('type', 'refund')->sole();
        $this->assertSame($second->id, $refund->cash_session_id);
        $this->assertSame($oldSale->id, $refund->sale_id);
        $this->assertEquals(60, (float) $refund->amount);
        $this->assertSame('cash', $refund->method);

        // A partial return with refund also leaves the current session.
        $this->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']]))
            ->assertSessionDoesntHaveErrors();
        $returnSale = Sale::query()->with('items')->latest('id')->firstOrFail();
        $this->post(route('sales.returns.store', $returnSale->id), [
            'refund_method' => 'pix',
            'refund_amount' => '50,00',
            'items' => [$returnSale->items->first()->id => ['quantity' => '1']],
        ])->assertSessionDoesntHaveErrors();

        $summary = app(CashSessionService::class)->summary($second->fresh());
        // 40 later receipt - 60 cancellation refund.
        $this->assertEquals(-20, $summary['cash']['expected']);
        $pixRow = $summary['methods']->firstWhere('kind', 'pix');
        $this->assertEquals(50, $pixRow['received']);
        $this->assertEquals(50, $pixRow['refunds']);
        $this->assertEquals(0, $pixRow['expected']);
    }

    public function test_cancelling_a_sale_gives_back_only_what_the_customer_still_has_paid(): void
    {
        $session = $this->openCashSession($this->operator, $this->clinic, 0);
        $this->actingAs($this->operator)
            ->post(route('sales.store'), $this->pdvSale(50, [['payment_method_id' => $this->method('cash')->id, 'amount' => '70,00']]))
            ->assertSessionDoesntHaveErrors();
        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertEquals(50, app(CashSessionService::class)->summary($session)['cash']['expected']);

        $this->patch(route('sales.cancel', $sale->id))->assertSessionDoesntHaveErrors();

        // 70 received - 20 change: 50 goes back, and the receipt stays in the
        // session where it happened.
        $refund = CashSessionMovement::query()->sole();
        $this->assertEquals(50, (float) $refund->amount);
        $this->assertEquals(0, app(CashSessionService::class)->summary($session->fresh())['cash']['expected']);

        // A sale partially returned before being cancelled only gives back
        // the rest.
        $this->post(route('sales.store'), [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'items' => [array_merge($this->item(50), ['quantity' => '2'])],
            'payments' => [['payment_method_id' => $this->method('pix')->id, 'amount' => '100,00']],
        ])->assertSessionDoesntHaveErrors();
        $returned = Sale::query()->with('items')->latest('id')->firstOrFail();
        $this->post(route('sales.returns.store', $returned->id), [
            'refund_method' => 'pix',
            'refund_amount' => '50,00',
            'items' => [$returned->items->first()->id => ['quantity' => '1']],
        ])->assertSessionDoesntHaveErrors();
        $this->patch(route('sales.cancel', $returned->id))->assertSessionDoesntHaveErrors();

        $this->assertEquals(
            [50.0, 50.0],
            CashSessionMovement::query()->where('sale_id', $returned->id)->orderBy('id')->pluck('amount')->map(fn ($amount) => (float) $amount)->all()
        );
        $pixRow = app(CashSessionService::class)->summary($session->fresh())['methods']->firstWhere('kind', 'pix');
        $this->assertEquals(0, $pixRow['expected']);

        $this->get(route('sales.cash-sessions.show', $session->id))
            ->assertOk()
            ->assertSee('venda cancelada');
    }

    public function test_sessions_stay_with_their_operator_and_clinic(): void
    {
        $session = $this->openCashSession($this->operator, $this->clinic, 10);
        $colleague = $this->userFor($this->clinic, ['sales.manage'], 'Bruno');

        $this->actingAs($colleague)->get(route('sales.cash-sessions.show', $session->id))->assertForbidden();
        $this->actingAs($colleague)
            ->post(route('sales.cash-sessions.movements.store', $session->id), ['type' => 'supply', 'amount' => '5', 'description' => 'x'])
            ->assertForbidden();
        $this->actingAs($colleague)
            ->get(route('sales.cash-sessions.index'))
            ->assertOk()
            ->assertDontSee('CX-000001');

        $otherClinic = $this->clinic('Outra Clinica', '00000000000702');
        $outsider = $this->userFor($otherClinic, ['sales.manage', 'cash-sessions.review'], 'Fora');

        $this->actingAs($outsider)->get(route('sales.cash-sessions.show', $session->id))->assertNotFound();
        $this->actingAs($outsider)->post(route('sales.cash-sessions.review', $session->id))->assertNotFound();

        // The colleague's own cash does not let them receive in the name of
        // the first operator, and each one gets a separate code.
        $this->actingAs($colleague)->postJson(route('sales.cash-sessions.store'), ['opening_amount' => '0'])->assertCreated();
        $this->assertSame(['CX-000001', 'CX-000002'], CashSession::query()->orderBy('id')->pluck('code')->all());
    }

    public function test_global_operator_opens_the_cash_of_the_selected_clinic(): void
    {
        $global = $this->userFor(null, ['sales.manage'], 'Suporte');

        $this->actingAs($global)
            ->postJson(route('sales.cash-sessions.store'), ['opening_amount' => '0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clinic_id');

        $this->postJson(route('sales.cash-sessions.store'), ['clinic_id' => $this->clinic->id, 'opening_amount' => '0'])
            ->assertCreated();

        $payload = $this->pdvSale(50, [['payment_method_id' => $this->method('pix')->id, 'amount' => '50,00']]);
        $payload['clinic_id'] = $this->clinic->id;

        $this->post(route('sales.store'), $payload)->assertSessionDoesntHaveErrors();
        $this->assertSame(
            CashSession::query()->where('user_id', $global->id)->value('id'),
            SalePayment::query()->latest('id')->value('cash_session_id')
        );
    }

    private function creditMachine(): PaymentMethod
    {
        app(PaymentMethodService::class)->ensureDefaults($this->clinic->id);

        return PaymentMethod::query()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Rede Visa Crédito',
            'kind' => 'credit_card',
            'acquirer' => 'Rede',
            'card_brand' => 'Visa',
            'fee_percent' => 5,
            'settlement_days' => 30,
            'max_installments' => 6,
            'active' => true,
        ]);
    }

    private function method(string $kind): PaymentMethod
    {
        return app(PaymentMethodService::class)->forClinic($this->clinic->id)->firstWhere('kind', $kind);
    }

    private function item(float $unitPrice): array
    {
        return [
            'type' => 'product',
            'product_id' => $this->product->id,
            'description' => $this->product->name,
            'quantity' => '1',
            'unit_price' => (string) $unitPrice,
        ];
    }

    private function pdvSale(float $unitPrice, array $payments): array
    {
        return [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'items' => [$this->item($unitPrice)],
            'payments' => $payments,
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

    private function userFor(?Clinic $clinic, array $permissionSlugs, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
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
