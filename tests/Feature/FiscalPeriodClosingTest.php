<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalPeriodClosure;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalPeriodClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_closure_is_blocked_until_every_sale_is_authorized(): void
    {
        [$clinic, $user] = $this->context('Clínica Fechamento', '00000000000881');
        $this->profile($clinic);
        $sale = $this->productSale($clinic, $user, 'VEN-FECHAMENTO', 100);

        $this->actingAs($user)->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Fechamento bloqueado')
            ->assertSee('Sem preparação: 1');

        $payload = $this->closurePayload('Responsável Fiscal');
        $this->post(route('fiscal.reconciliation.closures.store'), $payload)
            ->assertSessionHasErrors('period');
        $this->assertDatabaseCount('fiscal_period_closures', 0);

        $this->document($sale, 'authorized', 100);
        $this->post(route('fiscal.reconciliation.closures.store'), $payload)
            ->assertRedirect(route('fiscal.reconciliation.index', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]));

        $closure = FiscalPeriodClosure::query()->sole();
        $this->assertSame('Responsável Fiscal', $closure->reviewer_name);
        $this->assertSame(1, data_get($closure->evidence_snapshot, 'totals.sales_count'));
        $this->assertSame(1, data_get($closure->evidence_snapshot, 'totals.statuses.authorized.count'));
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.period-closure.recorded',
        ]);

        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Período fechado com evidência vigente')
            ->assertSee('Responsável Fiscal');

        $this->post(route('fiscal.reconciliation.closures.store'), $payload)
            ->assertSessionHasErrors('period');
        $this->assertDatabaseCount('fiscal_period_closures', 1);
    }

    public function test_closure_becomes_stale_after_period_change_and_history_is_tenant_scoped(): void
    {
        [$clinic, $user] = $this->context('Clínica Evidência', '00000000000882');
        $this->profile($clinic);
        $first = $this->productSale($clinic, $user, 'VEN-PRIMEIRA', 60);
        $this->document($first, 'authorized', 60);

        $this->actingAs($user)->post(
            route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável Interno')
        )->assertSessionHasNoErrors();

        $second = $this->productSale($clinic, $user, 'VEN-RETROATIVA', 40);
        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Fechamento desatualizado')
            ->assertSee('Fechamento bloqueado');

        [$otherClinic, $otherUser] = $this->context('Clínica Externa', '00000000000883');
        $this->profile($otherClinic);
        $external = $this->productSale($otherClinic, $otherUser, 'VEN-EXTERNA', 80);
        $this->document($external, 'authorized', 80);
        $this->actingAs($otherUser)->post(
            route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável Externo')
        )->assertSessionHasNoErrors();
        $externalClosure = FiscalPeriodClosure::query()->where('clinic_id', $otherClinic->id)->sole();

        $this->actingAs($user)->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Responsável Interno')
            ->assertDontSee('Responsável Externo');
        $this->get(route('fiscal.reconciliation.closures.index', ['competence' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Responsável Interno')
            ->assertDontSee('Responsável Externo');
        $this->get(route('fiscal.reconciliation.closures.dossier', $externalClosure->ulid))->assertNotFound();
        $this->get(route('fiscal.reconciliation.closures.dossier.json', $externalClosure->ulid))->assertNotFound();

        $this->document($second, 'authorized', 40);
        $this->post(
            route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável Revisor')
        )->assertSessionHasNoErrors();

        $this->assertSame(2, FiscalPeriodClosure::query()->where('clinic_id', $clinic->id)->count());
        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Período fechado com evidência vigente')
            ->assertSee('Responsável Revisor');
    }

    public function test_each_closure_has_a_printable_and_safe_json_dossier(): void
    {
        [$clinic, $user] = $this->context('Clínica Dossiê Fechamento', '00000000000886');
        $this->profile($clinic);
        $sale = $this->productSale($clinic, $user, 'VEN-DOSSIE-FECHAMENTO', 125);
        $this->document($sale, 'authorized', 125);

        $this->actingAs($user)->post(
            route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável pelo Dossiê')
        )->assertSessionHasNoErrors();

        $closure = FiscalPeriodClosure::query()->sole();
        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Abrir dossiê')
            ->assertSee(route('fiscal.reconciliation.closures.dossier', $closure->ulid));
        $this->get(route('fiscal.reconciliation.closures.dossier', $closure->ulid))
            ->assertOk()
            ->assertSee('Dossiê do fechamento fiscal')
            ->assertSee('Fechamento vigente.')
            ->assertSee('Responsável pelo Dossiê')
            ->assertSee('VEN-DOSSIE-FECHAMENTO')
            ->assertSee('R$ 125,00')
            ->assertSee($closure->evidence_fingerprint);

        $jsonResponse = $this->get(route('fiscal.reconciliation.closures.dossier.json', $closure->ulid))
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $json = $jsonResponse->streamedContent();
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('fiscal-period-closure-dossier-v1', $payload['schema_version']);
        $this->assertSame($closure->ulid, $payload['closure']['id']);
        $this->assertTrue($payload['status']['current']);
        $this->assertTrue($payload['status']['current_reconciliation_complete']);
        $this->assertFalse($payload['scope']['automatic_fiscal_action']);
        $this->assertSame(1, $payload['evidence']['totals']['sales_count']);
        $this->assertStringNotContainsString('request_payload', $json);
        $this->assertStringNotContainsString('idempotency_key', $json);

        $this->productSale($clinic, $user, 'VEN-POSTERIOR', 25);

        $this->get(route('fiscal.reconciliation.closures.dossier', $closure->ulid))
            ->assertOk()
            ->assertSee('Fechamento superado.')
            ->assertSee('VEN-DOSSIE-FECHAMENTO')
            ->assertDontSee('VEN-POSTERIOR');
        $stalePayload = json_decode(
            $this->get(route('fiscal.reconciliation.closures.dossier.json', $closure->ulid))->streamedContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertFalse($stalePayload['status']['current']);
        $this->assertFalse($stalePayload['status']['current_reconciliation_complete']);
        $this->assertSame(1, $stalePayload['evidence']['totals']['sales_count']);
    }

    public function test_closure_history_filters_the_competence_reviewer_and_current_state(): void
    {
        [$clinic, $user] = $this->context('Clínica Histórico Fiscal', '00000000000887');
        $this->profile($clinic);
        $firstSale = $this->productSale($clinic, $user, 'VEN-HISTORICO-1', 90);
        $this->document($firstSale, 'authorized', 90);

        $this->actingAs($user)->post(
            route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável Inicial')
        )->assertSessionHasNoErrors();

        $secondSale = $this->productSale($clinic, $user, 'VEN-HISTORICO-2', 35);
        $this->document($secondSale, 'authorized', 35);
        $this->post(
            route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável Atual')
        )->assertSessionHasNoErrors();

        $competence = now()->format('Y-m');
        $this->get(route('fiscal.reconciliation.closures.index', ['competence' => $competence]))
            ->assertOk()
            ->assertSee('Histórico de fechamentos fiscais')
            ->assertSee('Responsável Inicial')
            ->assertSee('Responsável Atual')
            ->assertSee('Vigente')
            ->assertSee('Superado')
            ->assertSee('R$ 215,00');

        $this->get(route('fiscal.reconciliation.closures.index', [
            'competence' => $competence,
            'state' => 'current',
        ]))
            ->assertOk()
            ->assertSee('Responsável Atual')
            ->assertDontSee('Responsável Inicial');
        $this->get(route('fiscal.reconciliation.closures.index', [
            'competence' => $competence,
            'state' => 'stale',
        ]))
            ->assertOk()
            ->assertSee('Responsável Inicial')
            ->assertDontSee('Responsável Atual');
        $this->get(route('fiscal.reconciliation.closures.index', [
            'competence' => $competence,
            'reviewer' => 'Atual',
        ]))
            ->assertOk()
            ->assertSee('Responsável Atual')
            ->assertDontSee('Responsável Inicial');
        $this->get(route('fiscal.reconciliation.closures.index', [
            'competence' => now()->subMonthNoOverflow()->format('Y-m'),
        ]))
            ->assertOk()
            ->assertSee('Nenhum fechamento foi encontrado');
    }

    public function test_closure_period_is_bounded_and_clinic_cannot_be_spoofed(): void
    {
        [$clinic, $user] = $this->context('Clínica Período', '00000000000884');
        $this->profile($clinic);
        [$otherClinic] = $this->context('Clínica Alvo', '00000000000885');

        $this->actingAs($user)->post(route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável', [
                'from' => now()->subDays(31)->toDateString(),
                'clinic_id' => $otherClinic->id,
            ])
        )->assertSessionHasErrors('to');

        $this->post(route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável', [
                'to' => now()->addDay()->toDateString(),
                'clinic_id' => $otherClinic->id,
            ])
        )->assertSessionHasErrors('to');

        $this->post(route('fiscal.reconciliation.closures.store'),
            $this->closurePayload('Responsável', ['clinic_id' => $otherClinic->id])
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fiscal_period_closures', ['clinic_id' => $clinic->id]);
        $this->assertDatabaseMissing('fiscal_period_closures', ['clinic_id' => $otherClinic->id]);
    }

    /** @return array<string, mixed> */
    private function closurePayload(string $reviewer, array $overrides = []): array
    {
        return $overrides + [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'reviewer_name' => $reviewer,
            'notes' => 'Conferência interna concluída.',
            'current_password' => 'password',
            'confirm_close' => '1',
        ];
    }

    /** @return array{Clinic, User} */
    private function context(string $name, string $cnpj): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            ['name' => 'Fiscal', 'description' => 'Fiscal', 'group' => 'Fiscal', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal '.Str::random(8),
            'slug' => 'fiscal-closing-'.Str::lower(Str::random(8)),
            'description' => 'Teste',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach($permission->id);
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clinic, $user];
    }

    private function profile(Clinic $clinic): FiscalProfile
    {
        return FiscalProfile::query()->create([
            'clinic_id' => $clinic->id,
            'enabled' => true,
            'environment' => 'homologation',
            'nfce_enabled' => true,
            'nfse_enabled' => false,
            'configuration_version' => 1,
        ]);
    }

    private function productSale(Clinic $clinic, User $user, string $code, int $total): Sale
    {
        $sale = Sale::query()->create([
            'clinic_id' => $clinic->id,
            'seller_user_id' => $user->id,
            'code' => $code,
            'status' => 'completed',
            'payment_status' => 'paid',
            'sold_at' => now(),
            'completed_at' => now(),
            'subtotal' => $total,
            'discount_total' => 0,
            'additions_total' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'paid_total' => $total,
            'change_total' => 0,
            'return_total' => 0,
            'refunded_total' => 0,
            'stock_applied' => false,
            'financial_applied' => false,
        ]);
        $product = Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto '.Str::random(5),
            'sale_price' => $total,
            'active' => true,
        ]);
        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'type' => 'product',
            'description' => 'Produto teste',
            'quantity' => 1,
            'unit_price' => $total,
            'discount_total' => 0,
            'gross_total' => $total,
            'net_total' => $total,
            'total' => $total,
        ]);

        return $sale;
    }

    private function document(Sale $sale, string $status, int $total): FiscalDocument
    {
        return FiscalDocument::query()->create([
            'clinic_id' => $sale->clinic_id,
            'sale_id' => $sale->id,
            'type' => 'nfce',
            'environment' => 'homologation',
            'provider' => null,
            'status' => $status,
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 3,
            'issuer_snapshot' => ['corporate_name' => 'Clínica teste'],
            'recipient_snapshot' => null,
            'items_snapshot' => [],
            'payments_snapshot' => [],
            'totals_snapshot' => ['total' => number_format($total, 2, '.', '')],
            'request_payload' => ['test' => true],
            'authorized_at' => $status === 'authorized' ? now() : null,
        ]);
    }
}
