<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Models\FiscalReturnReview;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleEvent;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalReturnReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_requires_human_review_and_completed_review_unblocks_closing(): void
    {
        [$clinic, $user] = $this->context('Clínica Devolução', '00000000000891');
        $this->profile($clinic);
        [$sale, $item] = $this->productSale($clinic, $user, 'VEN-DEVOLUCAO', 100);
        $this->document($sale, 100);
        $this->applyReturn($sale, $item, 1, 30, false);

        $this->actingAs($user)->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('VEN-DEVOLUCAO')
            ->assertSee('A venda possui devolução ou reembolso ainda sem revisão fiscal humana.')
            ->assertSee('Revisar devolução');

        $this->get(route('fiscal.returns.show', $sale->id))
            ->assertOk()
            ->assertSee('Revisão fiscal da devolução')
            ->assertSee('Produto devolvido')
            ->assertSee('R$ 30,00');

        $this->post(route('fiscal.returns.reviews.store', $sale->id), $this->reviewPayload(
            'follow_up_required',
            'Aguardando o escritório confirmar qual procedimento manual deve ser adotado.'
        ))->assertRedirect(route('fiscal.returns.show', $sale->id));

        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('A revisão atual ainda indica acompanhamento necessário.');

        $this->post(route('fiscal.returns.reviews.store', $sale->id), $this->reviewPayload(
            'review_completed',
            'Orientação conferida com a contabilidade e procedimento externo registrado pela clínica.'
        ))->assertRedirect(route('fiscal.returns.show', $sale->id));

        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('Devolução revisada')
            ->assertSee('A devolução ou o reembolso possui revisão humana concluída e vigente.');

        $this->post(route('fiscal.reconciliation.closures.store'), $this->closurePayload())
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fiscal_return_reviews', 2);
        $this->assertDatabaseCount('fiscal_period_closures', 1);
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.return-review.recorded',
        ]);
    }

    public function test_review_becomes_stale_after_another_return_and_fully_returned_sale_stays_visible(): void
    {
        [$clinic, $user] = $this->context('Clínica Evidência', '00000000000892');
        $this->profile($clinic);
        [$sale, $item] = $this->productSale($clinic, $user, 'VEN-INTEGRAL', 100);
        $this->document($sale, 100);
        $this->applyReturn($sale, $item, 1, 40, false);

        $this->actingAs($user)->post(
            route('fiscal.returns.reviews.store', $sale->id),
            $this->reviewPayload('review_completed', 'Primeira devolução conferida conforme a orientação interna registrada.')
        )->assertSessionHasNoErrors();

        $this->applyReturn($sale->fresh(), $item->fresh(), 1, 60, true);

        $this->get(route('fiscal.returns.show', $sale->id))
            ->assertOk()
            ->assertSee('Revisão desatualizada');
        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('VEN-INTEGRAL')
            ->assertSee('A revisão anterior ficou desatualizada');

        $this->post(
            route('fiscal.returns.reviews.store', $sale->id),
            $this->reviewPayload('review_completed', 'Devolução integral revisada novamente após a alteração da evidência da venda.')
        )->assertSessionHasNoErrors();

        $this->assertSame(2, FiscalReturnReview::query()->where('sale_id', $sale->id)->count());
        $this->get(route('fiscal.reconciliation.index'))
            ->assertOk()
            ->assertSee('VEN-INTEGRAL')
            ->assertSee('Devolução revisada');
    }

    public function test_each_review_has_a_printable_and_safe_json_dossier(): void
    {
        [$clinic, $user] = $this->context('Clínica Dossiê', '00000000000895');
        $this->profile($clinic);
        [$sale, $item] = $this->productSale($clinic, $user, 'VEN-DOSSIE', 100);
        $this->document($sale, 100);
        $this->applyReturn($sale, $item, 1, 30, false);

        $this->actingAs($user)->post(
            route('fiscal.returns.reviews.store', $sale->id),
            $this->reviewPayload(
                'review_completed',
                'Procedimento externo conferido e documentado para compor o dossiê desta devolução.'
            )
        )->assertSessionHasNoErrors();

        $review = FiscalReturnReview::query()->where('sale_id', $sale->id)->sole();
        $this->get(route('fiscal.returns.show', $sale->id))
            ->assertOk()
            ->assertSee('Abrir último dossiê')
            ->assertSee(route('fiscal.returns.dossier', [$sale->id, $review->ulid]));
        $this->get(route('fiscal.returns.dossier', [$sale->id, $review->ulid]))
            ->assertOk()
            ->assertSee('Dossiê fiscal da devolução')
            ->assertSee('Revisão concluída e vigente.')
            ->assertSee('Escritório contábil de teste')
            ->assertSee('R$ 30,00')
            ->assertSee($review->evidence_fingerprint);

        $jsonResponse = $this->get(route('fiscal.returns.dossier.json', [$sale->id, $review->ulid]))
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $json = $jsonResponse->streamedContent();
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('fiscal-return-dossier-v1', $payload['schema_version']);
        $this->assertSame($review->ulid, $payload['review']['id']);
        $this->assertTrue($payload['status']['operationally_resolved']);
        $this->assertTrue($payload['fingerprints']['matches_current']);
        $this->assertFalse($payload['scope']['automatic_fiscal_action']);
        $this->assertStringNotContainsString('request_payload', $json);
        $this->assertStringNotContainsString('idempotency_key', $json);

        $this->applyReturn($sale->fresh(), $item->fresh(), 1, 70, true);

        $this->get(route('fiscal.returns.dossier', [$sale->id, $review->ulid]))
            ->assertOk()
            ->assertSee('Evidência superada.')
            ->assertSee('R$ 30,00');
        $stalePayload = json_decode(
            $this->get(route('fiscal.returns.dossier.json', [$sale->id, $review->ulid]))->streamedContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertFalse($stalePayload['status']['current']);
        $this->assertFalse($stalePayload['status']['operationally_resolved']);
    }

    public function test_review_is_password_protected_and_tenant_scoped(): void
    {
        [$clinic, $user] = $this->context('Clínica Segura', '00000000000893');
        $this->profile($clinic);
        [$sale, $item] = $this->productSale($clinic, $user, 'VEN-SEGURA', 50);
        $this->applyReturn($sale, $item, 1, 20, false);

        [$otherClinic, $otherUser] = $this->context('Clínica Externa', '00000000000894');
        [$externalSale, $externalItem] = $this->productSale($otherClinic, $otherUser, 'VEN-EXTERNA', 70);
        $this->applyReturn($externalSale, $externalItem, 1, 20, false);

        $this->actingAs($otherUser)->post(
            route('fiscal.returns.reviews.store', $externalSale->id),
            $this->reviewPayload('review_completed', 'Revisão exclusiva da outra clínica para validar o isolamento do dossiê.')
        )->assertSessionHasNoErrors();
        $externalReview = FiscalReturnReview::query()->where('sale_id', $externalSale->id)->sole();

        $this->actingAs($user)->get(route('fiscal.returns.show', $externalSale->id))->assertNotFound();
        $this->get(route('fiscal.returns.dossier', [$externalSale->id, $externalReview->ulid]))->assertNotFound();
        $this->get(route('fiscal.returns.dossier.json', [$externalSale->id, $externalReview->ulid]))->assertNotFound();
        $this->post(route('fiscal.returns.reviews.store', $sale->id), $this->reviewPayload(
            'review_completed',
            'Orientação válida registrada para verificar a proteção por senha.',
            ['current_password' => 'senha-incorreta']
        ))->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('fiscal_return_reviews', 1);

        [$cleanSale] = $this->productSale($clinic, $user, 'VEN-SEM-DEVOLUCAO', 30);
        $this->get(route('fiscal.returns.show', $cleanSale->id))->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function reviewPayload(string $decision, string $summary, array $overrides = []): array
    {
        return $overrides + [
            'decision' => $decision,
            'reviewer_name' => 'Responsável Fiscal',
            'guidance_source' => 'Escritório contábil de teste',
            'resolution_summary' => $summary,
            'current_password' => 'password',
            'confirm_review' => '1',
        ];
    }

    /** @return array<string, mixed> */
    private function closurePayload(): array
    {
        return [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'reviewer_name' => 'Responsável Fiscal',
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
            'slug' => 'fiscal-return-'.Str::lower(Str::random(8)),
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

    /** @return array{Sale, SaleItem} */
    private function productSale(Clinic $clinic, User $user, string $code, int $total): array
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
            'sale_price' => $total / 2,
            'active' => true,
        ]);
        $item = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'type' => 'product',
            'description' => 'Produto devolvido',
            'quantity' => 2,
            'unit_price' => $total / 2,
            'discount_total' => 0,
            'gross_total' => $total,
            'net_total' => $total,
            'returned_quantity' => 0,
            'refunded_total' => 0,
            'total' => $total,
        ]);

        return [$sale, $item];
    }

    private function applyReturn(Sale $sale, SaleItem $item, int $quantity, int $amount, bool $complete): void
    {
        $item->update([
            'returned_quantity' => (float) $item->returned_quantity + $quantity,
            'refunded_total' => (float) $item->refunded_total + $amount,
        ]);
        $sale->update([
            'status' => $complete ? 'returned' : 'completed',
            'return_total' => (float) $sale->return_total + $amount,
            'refunded_total' => (float) $sale->refunded_total + $amount,
        ]);
        SaleEvent::query()->create([
            'sale_id' => $sale->id,
            'sale_item_id' => $item->id,
            'event_type' => $complete ? 'returned' : 'partial_return',
            'quantity' => $quantity,
            'amount' => $amount,
            'reason' => $complete ? 'Devolução integral' : 'Devolução parcial',
            'occurred_at' => now(),
        ]);
    }

    private function document(Sale $sale, int $total): FiscalDocument
    {
        return FiscalDocument::query()->create([
            'clinic_id' => $sale->clinic_id,
            'sale_id' => $sale->id,
            'type' => 'nfce',
            'environment' => 'homologation',
            'provider' => null,
            'status' => 'authorized',
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 3,
            'issuer_snapshot' => ['corporate_name' => 'Clínica teste'],
            'recipient_snapshot' => null,
            'items_snapshot' => [],
            'payments_snapshot' => [],
            'totals_snapshot' => ['total' => number_format($total, 2, '.', '')],
            'request_payload' => ['test' => true],
            'authorized_at' => now(),
        ]);
    }
}
