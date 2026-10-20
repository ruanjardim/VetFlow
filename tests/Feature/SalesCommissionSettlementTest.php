<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Commissions\Models\CommissionRule;
use App\Modules\Commissions\Models\SalesCommissionSettlement;
use App\Modules\Commissions\Models\SalesCommissionSettlementItem;
use App\Modules\Commissions\Services\CommissionService;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesCommissionSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_details_the_amount_available_to_close(): void
    {
        [$clinic, $manager, $seller] = $this->scenario();
        $sale = $this->sale($clinic, $seller, 'VEN-COM-001', 100, 40);
        $this->rule($clinic, $seller, percentage: 10);

        $this->actingAs($manager)
            ->get(route('commissions.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Fechar por vendedor')
            ->assertSee('Composição disponível')
            ->assertSee($sale->code)
            ->assertSee('R$ 10,00')
            ->assertSee('Fechar e gerar conta');
    }

    public function test_settlement_creates_an_immutable_snapshot_payable_and_audit_event(): void
    {
        [$clinic, $manager, $seller] = $this->scenario();
        $sale = $this->sale($clinic, $seller, 'VEN-COM-002', 100, 40);
        $rule = $this->rule($clinic, $seller, percentage: 10);
        $payload = $this->settlementPayload($seller);

        $this->actingAs($manager)
            ->post(route('commissions.settle'), $payload)
            ->assertRedirect(route('commissions.index', ['from' => $payload['from'], 'to' => $payload['to']]))
            ->assertSessionDoesntHaveErrors();

        $settlement = SalesCommissionSettlement::query()->with(['items', 'financialTransaction'])->sole();
        $item = $settlement->items->sole();
        $this->assertSame($clinic->id, (int) $settlement->clinic_id);
        $this->assertSame($seller->id, (int) $settlement->seller_user_id);
        $this->assertSame('10.00', (string) $settlement->total);
        $this->assertSame(64, strlen($settlement->snapshot_hash));
        $this->assertSame($rule->id, (int) $item->commission_rule_id);
        $this->assertSame($sale->id, (int) $item->sale_id);
        $this->assertSame('10.00', (string) $item->amount);
        $this->assertSame('expense', $settlement->financialTransaction->type);
        $this->assertSame('pending', $settlement->financialTransaction->status);
        $this->assertSame(FinancialCategory::CODE_COMMISSIONS, $settlement->financialTransaction->category()->value('system_code'));
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'commission.sales-settlement.created',
            'subject_id' => (string) $settlement->id,
        ]);

        $this->post(route('commissions.settle'), $payload)
            ->assertSessionHasErrors('seller_user_id');
        $this->assertSame(1, SalesCommissionSettlement::query()->count());
        $this->assertSame(1, SalesCommissionSettlementItem::query()->count());
        $this->assertSame(1, FinancialTransaction::query()->where('reference', 'COMISSAO-VENDAS')->count());
    }

    public function test_partial_receipts_are_closed_once_and_later_receipts_remain_available(): void
    {
        [$clinic, $manager, $seller] = $this->scenario();
        $sale = $this->sale($clinic, $seller, 'VEN-COM-003', 100, 40, paymentStatus: 'partial');
        $this->rule($clinic, $seller, percentage: 10, recognition: 'receipt_date', requiresPaid: false);
        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'method' => 'pix',
            'amount' => 40,
            'status' => 'paid',
            'paid_at' => today()->setTime(9, 0),
        ]);

        $this->actingAs($manager)->post(route('commissions.settle'), $this->settlementPayload($seller));
        $this->assertSame('4.00', (string) SalesCommissionSettlement::query()->sole()->total);

        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'method' => 'pix',
            'amount' => 60,
            'status' => 'paid',
            'paid_at' => today()->addDay()->setTime(9, 0),
        ]);
        $preview = app(CommissionService::class)->preview(today()->toDateString(), today()->addDay()->toDateString());

        $this->assertEquals(6.0, (float) $preview['summary']['pending_amount']);
        $this->assertSame(1, $preview['summary']['pending_count']);

        $payload = $this->settlementPayload($seller, today()->toDateString(), today()->addDay()->toDateString());
        $this->post(route('commissions.settle'), $payload)->assertSessionDoesntHaveErrors();
        $this->assertEqualsCanonicalizing(['4.00', '6.00'], SalesCommissionSettlement::query()->pluck('total')->map(fn ($value) => (string) $value)->all());
    }

    public function test_return_after_closing_becomes_a_negative_adjustment_in_the_next_settlement(): void
    {
        [$clinic, $manager, $seller] = $this->scenario();
        $first = $this->sale($clinic, $seller, 'VEN-COM-004', 100, 40);
        $this->rule($clinic, $seller, percentage: 10);
        $this->actingAs($manager)->post(route('commissions.settle'), $this->settlementPayload($seller));

        $first->update(['return_total' => 20]);
        $this->sale($clinic, $seller, 'VEN-COM-005', 50, 20);
        $preview = app(CommissionService::class)->preview(today()->toDateString(), today()->toDateString());

        $this->assertEquals(3.0, (float) $preview['summary']['pending_amount']);
        $this->assertSame(1, $preview['summary']['adjustments_count']);
        $this->assertEqualsCanonicalizing([-2.0, 5.0], $preview['contributions']->pluck('pending_amount')->all());

        $this->post(route('commissions.settle'), $this->settlementPayload($seller))->assertSessionDoesntHaveErrors();
        $second = SalesCommissionSettlement::query()->latest('id')->firstOrFail();
        $this->assertSame('3.00', (string) $second->total);
        $this->assertEqualsCanonicalizing(
            ['-2.00', '5.00'],
            $second->items()->pluck('amount')->map(fn ($value) => (string) $value)->all()
        );
    }

    public function test_tenant_boundary_and_financial_origin_protection_are_enforced(): void
    {
        [$clinicA, $managerA, $sellerA] = $this->scenario('Comissão A', '00000000000951');
        [$clinicB, $managerB, $sellerB] = $this->scenario('Comissão B', '00000000000952');
        $this->sale($clinicA, $sellerA, 'VEN-COM-A', 100, 40);
        $this->rule($clinicA, $sellerA, percentage: 10);
        $this->sale($clinicB, $sellerB, 'VEN-COM-B', 200, 80);
        $this->rule($clinicB, $sellerB, percentage: 10);

        $external = $this->settlementPayload($sellerB);
        $this->actingAs($managerA)->post(route('commissions.settle'), $external)
            ->assertSessionHasErrors('seller_user_id');
        $this->assertSame(0, SalesCommissionSettlement::query()->count());

        $this->actingAs($managerB)->post(route('commissions.settle'), $this->settlementPayload($sellerB));
        $transaction = FinancialTransaction::query()->where('reference', 'COMISSAO-VENDAS')->sole();

        $this->from(route('financial-transactions.index'))
            ->get(route('financial-transactions.edit', $transaction->id))
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionHas('error');
        $this->from(route('financial-transactions.index'))
            ->patch(route('financial-transactions.cancel', $transaction->id))
            ->assertSessionHasErrors('transaction');
        $this->from(route('financial-transactions.index'))
            ->delete(route('financial-transactions.destroy', $transaction->id))
            ->assertSessionHasErrors('transaction');
        $this->patch(route('financial-transactions.pay', $transaction->id))
            ->assertRedirect(route('financial-transactions.index'));
        $this->assertSame('paid', $transaction->refresh()->status);
        $this->get(route('financial-obligations.index', [
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->endOfMonth()->toDateString(),
            'type' => 'expense',
            'status' => 'paid',
        ]))->assertOk()->assertSee('Comissão de vendas')->assertSee($sellerB->name);
        $this->assertSame(1, AuditEvent::query()->where('event', 'commission.sales-settlement.created')->count());
    }

    /** @return array{Clinic, User, User} */
    private function scenario(string $name = 'Comissão Teste', string $cnpj = '00000000000950'): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
        $manager = $this->user($clinic, ['commissions.manage', 'financial.manage', 'sales.manage']);
        $seller = $this->user($clinic, ['sales.manage']);

        return [$clinic, $manager, $seller];
    }

    private function user(Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $role = Role::query()->create([
            'name' => 'Comissão '.Str::random(6),
            'slug' => 'commission-'.Str::lower(Str::random(8)),
            'active' => true,
            'system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::headline($slug), 'group' => 'Tests', 'active' => true]
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

    private function sale(
        Clinic $clinic,
        User $seller,
        string $code,
        float $total,
        float $profit,
        string $paymentStatus = 'paid',
    ): Sale {
        return Sale::query()->create([
            'clinic_id' => $clinic->id,
            'seller_user_id' => $seller->id,
            'code' => $code,
            'status' => 'completed',
            'payment_status' => $paymentStatus,
            'sold_at' => now(),
            'completed_at' => now(),
            'subtotal' => $total,
            'total' => $total,
            'paid_total' => $paymentStatus === 'paid' ? $total : 0,
            'gross_profit_total' => $profit,
            'return_total' => 0,
        ]);
    }

    private function rule(
        Clinic $clinic,
        User $seller,
        float $percentage,
        string $recognition = 'sale_date',
        bool $requiresPaid = true,
    ): CommissionRule {
        return CommissionRule::query()->create([
            'clinic_id' => $clinic->id,
            'seller_user_id' => $seller->id,
            'name' => 'Regra '.$seller->name,
            'percentage' => $percentage,
            'basis' => 'sold_total',
            'recognition' => $recognition,
            'requires_paid' => $requiresPaid,
            'starts_on' => today(),
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function settlementPayload(User $seller, ?string $from = null, ?string $to = null): array
    {
        return [
            'seller_user_id' => $seller->id,
            'from' => $from ?? today()->toDateString(),
            'to' => $to ?? today()->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
            'notes' => 'Fechamento conferido no teste.',
            'confirm_settlement' => '1',
        ];
    }
}
