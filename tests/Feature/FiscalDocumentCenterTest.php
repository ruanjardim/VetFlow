<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Sales\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalDocumentCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_center_filters_documents_by_competence_type_status_sale_and_period(): void
    {
        CarbonImmutable::setTestNow('2026-10-11 12:00:00');
        [$clinic, $user] = $this->context('Clínica Central Fiscal', '00000000000931');

        $nfceSale = $this->sale($clinic, $user, 'VEN-NFCE-001', 125.34);
        $this->document($nfceSale, 'nfce', 'authorized', 125.34, '2026-10-05 09:30:00');

        $nfseSale = $this->sale($clinic, $user, 'VEN-NFSE-002', 50);
        $this->document($nfseSale, 'nfse', 'draft', 50, '2026-10-20 15:00:00');

        $oldSale = $this->sale($clinic, $user, 'VEN-SETEMBRO', 75);
        $this->document($oldSale, 'nfce', 'rejected', 75, '2026-09-30 10:00:00');

        [$otherClinic, $otherUser] = $this->context('Clínica Central Externa', '00000000000932');
        $otherSale = $this->sale($otherClinic, $otherUser, 'VEN-EXTERNA', 999);
        $this->document($otherSale, 'nfce', 'authorized', 999, '2026-10-06 10:00:00');

        $this->actingAs($user)->get(route('fiscal.documents.index'))
            ->assertOk()
            ->assertSee('Central de documentos fiscais')
            ->assertSee('Competência operacional')
            ->assertSee('VEN-NFCE-001')
            ->assertSee('VEN-NFSE-002')
            ->assertDontSee('VEN-SETEMBRO')
            ->assertDontSee('VEN-EXTERNA');

        $this->get(route('fiscal.documents.index', [
            'competence' => '2026-10',
            'from' => '2026-10-01',
            'to' => '2026-10-10',
            'type' => 'nfce',
            'status' => 'authorized',
            'sale' => 'NFCE-001',
        ]))
            ->assertOk()
            ->assertSee('VEN-NFCE-001')
            ->assertDontSee('VEN-NFSE-002')
            ->assertDontSee('VEN-EXTERNA');

        $this->from(route('fiscal.documents.index'))->get(route('fiscal.documents.index', [
            'competence' => '2026-10',
            'from' => '2026-09-30',
            'to' => '2026-10-10',
        ]))
            ->assertRedirect(route('fiscal.documents.index'))
            ->assertSessionHasErrors('from');
    }

    public function test_csv_export_is_private_formula_safe_tenant_scoped_and_excludes_payloads(): void
    {
        CarbonImmutable::setTestNow('2026-10-11 12:00:00');
        [$clinic, $user] = $this->context('Clínica Exportação Fiscal', '00000000000933');
        $sale = $this->sale($clinic, $user, '=FORMULA', 90);
        $document = $this->document($sale, 'nfce', 'authorized', 90, '2026-10-07 11:00:00');
        $document->update([
            'document_number' => '=DANGEROUS',
            'series' => '1',
            'access_key' => '12345678901234567890123456789012345678901234',
            'request_payload' => ['secret_marker' => 'NAO_EXPORTAR_PAYLOAD'],
            'response_payload' => ['token' => 'NAO_EXPORTAR_TOKEN'],
        ]);

        [$otherClinic, $otherUser] = $this->context('Clínica Exportação Externa', '00000000000934');
        $otherSale = $this->sale($otherClinic, $otherUser, 'VEN-VAZAMENTO', 400);
        $this->document($otherSale, 'nfse', 'draft', 400, '2026-10-08 11:00:00');

        $response = $this->actingAs($user)->get(route('fiscal.documents.export', [
            'competence' => '2026-10',
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-robots-tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=FORMULA", $csv);
        $this->assertStringContainsString("'=DANGEROUS", $csv);
        $this->assertStringContainsString("'12345678901234567890123456789012345678901234", $csv);
        $this->assertStringNotContainsString('NAO_EXPORTAR_PAYLOAD', $csv);
        $this->assertStringNotContainsString('NAO_EXPORTAR_TOKEN', $csv);
        $this->assertStringNotContainsString('VEN-VAZAMENTO', $csv);
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
            'slug' => 'fiscal-document-center-'.Str::lower(Str::random(8)),
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

    private function sale(Clinic $clinic, User $user, string $code, float $total): Sale
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

    private function document(
        Sale $sale,
        string $type,
        string $status,
        float $total,
        string $createdAt,
    ): FiscalDocument {
        return FiscalDocument::query()->create([
            'clinic_id' => $sale->clinic_id,
            'sale_id' => $sale->id,
            'created_by_user_id' => $sale->seller_user_id,
            'type' => $type,
            'environment' => 'homologation',
            'provider' => 'focus_nfe',
            'status' => $status,
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 3,
            'issuer_snapshot' => ['corporate_name' => 'Clínica teste'],
            'recipient_snapshot' => null,
            'items_snapshot' => [],
            'payments_snapshot' => [],
            'totals_snapshot' => ['total' => number_format($total, 2, '.', '')],
            'request_payload' => $status === 'draft' ? null : ['test' => true],
            'authorized_at' => $status === 'authorized' ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
