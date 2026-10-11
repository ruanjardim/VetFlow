<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Services\FiscalReconciliationService;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_reconciliation_classifies_sales_without_claiming_tax_obligation(): void
    {
        [$clinic, $user] = $this->context('Clínica Conciliação', '00000000000871');
        $this->profile($clinic);

        $withoutPreparation = $this->productSale($clinic, $user, 'VEN-SEM-DOC', 80);

        $authorized = $this->serviceSale($clinic, $user, 'VEN-AUTORIZADA', 100);
        $this->document($authorized, 'nfse', 'authorized', 100, false);

        $mixed = $this->mixedSale($clinic, $user, 'VEN-RATEIO', 60, 40);
        $this->document($mixed, 'nfce', 'draft', 60, true);
        $this->document($mixed, 'nfse', 'draft', 40, true);

        $rejected = $this->productSale($clinic, $user, '=RISCO', 70);
        $this->document($rejected, 'nfce', 'rejected', 70, false);

        [$otherClinic, $otherUser] = $this->context('Clínica Externa', '00000000000872');
        $this->productSale($otherClinic, $otherUser, 'VEN-EXTERNA', 999);

        $response = $this->actingAs($user)->get(route('fiscal.reconciliation.index'));
        $response->assertOk()
            ->assertSee('Conciliação fiscal diária')
            ->assertSee('Vendas concluídas')
            ->assertSee('VEN-SEM-DOC')
            ->assertSee('VEN-AUTORIZADA')
            ->assertSee('VEN-RATEIO')
            ->assertSee('=RISCO')
            ->assertSee('Sem preparação')
            ->assertSee('Rateio pendente')
            ->assertSee('Autorizada')
            ->assertSee('Ação necessária')
            ->assertSee('significa apenas que o VetFlow não encontrou um documento vinculado', false)
            ->assertDontSee('VEN-EXTERNA')
            ->assertDontSee('999,00');

        $this->get(route('fiscal.reconciliation.index', ['status' => 'authorized']))
            ->assertOk()
            ->assertSee('VEN-AUTORIZADA')
            ->assertDontSee('VEN-SEM-DOC')
            ->assertDontSee('VEN-RATEIO')
            ->assertDontSee('=RISCO');

        $this->assertNotNull($withoutPreparation->completed_at);
    }

    public function test_csv_is_tenant_scoped_formula_safe_and_period_is_bounded(): void
    {
        [$clinic, $user] = $this->context('Clínica CSV', '00000000000873');
        $this->profile($clinic);
        $sale = $this->productSale($clinic, $user, '=FORMULA', 70);
        $this->document($sale, 'nfce', 'rejected', 70, false);

        [$otherClinic, $otherUser] = $this->context('Clínica CSV Externa', '00000000000874');
        $this->productSale($otherClinic, $otherUser, 'VEN-VAZAMENTO', 500);

        $response = $this->actingAs($user)->get(route('fiscal.reconciliation.export', [
            'status' => 'action_required',
        ]))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=FORMULA", $csv);
        $this->assertStringNotContainsString('VEN-VAZAMENTO', $csv);

        $this->from(route('fiscal.reconciliation.index'))->get(route('fiscal.reconciliation.index', [
            'from' => now()->subDays(31)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertRedirect(route('fiscal.reconciliation.index'))
            ->assertSessionHasErrors('to');
    }

    public function test_remaining_operational_states_are_deterministic(): void
    {
        [$clinic, $user] = $this->context('Clínica Estados', '00000000000875');
        $profile = $this->profile($clinic);

        $unmapped = $this->sale($clinic, $user, 'VEN-AVULSA', 10);
        $this->item($unmapped, 'other', 10, []);

        $returned = $this->productSale($clinic, $user, 'VEN-DEVOLUCAO', 20);
        $returned->update(['return_total' => 5, 'refunded_total' => 5]);

        $payload = $this->productSale($clinic, $user, 'VEN-PAYLOAD', 30);
        $this->document($payload, 'nfce', 'draft', 30, false);

        $submission = $this->productSale($clinic, $user, 'VEN-ENVIO', 40);
        $this->document($submission, 'nfce', 'draft', 40, false)
            ->update(['request_payload' => ['focus_nfce' => ['payload' => ['numero' => 1]]]]);

        $processing = $this->productSale($clinic, $user, 'VEN-PROCESSANDO', 50);
        $this->document($processing, 'nfce', 'queued', 50, false);

        $divergent = $this->productSale($clinic, $user, 'VEN-DIVERGENTE', 60);
        $this->document($divergent, 'nfce', 'authorized', 59, false);

        $report = app(FiscalReconciliationService::class)->report(
            $clinic->id,
            $profile,
            CarbonImmutable::today(),
            CarbonImmutable::today(),
        );
        $statuses = $report['rows']->pluck('status', 'sale_code');

        $this->assertSame('action_required', $statuses['VEN-AVULSA']);
        $this->assertSame('action_required', $statuses['VEN-DEVOLUCAO']);
        $this->assertSame('payload_pending', $statuses['VEN-PAYLOAD']);
        $this->assertSame('submission_pending', $statuses['VEN-ENVIO']);
        $this->assertSame('processing', $statuses['VEN-PROCESSANDO']);
        $this->assertSame('action_required', $statuses['VEN-DIVERGENTE']);

        $profile->update(['nfce_enabled' => false]);
        $configuration = app(FiscalReconciliationService::class)->report(
            $clinic->id,
            $profile->fresh(),
            CarbonImmutable::today(),
            CarbonImmutable::today(),
        );
        $this->assertSame(
            'configuration_pending',
            $configuration['rows']->firstWhere('sale_code', 'VEN-PAYLOAD')['status']
        );
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
            'slug' => 'fiscal-reconciliation-'.Str::lower(Str::random(8)),
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
            'nfse_enabled' => true,
            'configuration_version' => 1,
        ]);
    }

    private function productSale(Clinic $clinic, User $user, string $code, int $total): Sale
    {
        $sale = $this->sale($clinic, $user, $code, $total);
        $product = Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto '.Str::random(5),
            'sale_price' => $total,
            'active' => true,
        ]);
        $this->item($sale, 'product', $total, ['product_id' => $product->id]);

        return $sale;
    }

    private function serviceSale(Clinic $clinic, User $user, string $code, int $total): Sale
    {
        $sale = $this->sale($clinic, $user, $code, $total);
        $service = PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Serviço '.Str::random(5),
            'base_price' => $total,
            'active' => true,
        ]);
        $this->item($sale, 'service', $total, ['petshop_service_id' => $service->id]);

        return $sale;
    }

    private function mixedSale(Clinic $clinic, User $user, string $code, int $productTotal, int $serviceTotal): Sale
    {
        $sale = $this->sale($clinic, $user, $code, $productTotal + $serviceTotal);
        $product = Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto misto',
            'sale_price' => $productTotal,
            'active' => true,
        ]);
        $service = PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Serviço misto',
            'base_price' => $serviceTotal,
            'active' => true,
        ]);
        $this->item($sale, 'product', $productTotal, ['product_id' => $product->id]);
        $this->item($sale, 'service', $serviceTotal, ['petshop_service_id' => $service->id]);

        return $sale;
    }

    private function sale(Clinic $clinic, User $user, string $code, int $total): Sale
    {
        return Sale::query()->create([
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
    }

    /** @param array<string, mixed> $links */
    private function item(Sale $sale, string $type, int $total, array $links): void
    {
        SaleItem::query()->create($links + [
            'sale_id' => $sale->id,
            'type' => $type,
            'description' => ucfirst($type).' teste',
            'quantity' => 1,
            'unit_price' => $total,
            'discount_total' => 0,
            'gross_total' => $total,
            'net_total' => $total,
            'total' => $total,
        ]);
    }

    private function document(Sale $sale, string $type, string $status, int $total, bool $requiresAllocation): FiscalDocument
    {
        return FiscalDocument::query()->create([
            'clinic_id' => $sale->clinic_id,
            'sale_id' => $sale->id,
            'type' => $type,
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
            'totals_snapshot' => [
                'total' => number_format($total, 2, '.', ''),
                'requires_allocation' => $requiresAllocation ? 'yes' : 'no',
            ],
            'request_payload' => $status === 'draft' ? null : ['test' => true],
            'authorized_at' => $status === 'authorized' ? now() : null,
        ]);
    }
}
