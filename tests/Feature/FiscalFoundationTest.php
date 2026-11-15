<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDpsSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Models\FiscalSaleAllocation;
use App\Modules\Fiscal\Models\ProductFiscalProfile;
use App\Modules\Fiscal\Models\ServiceFiscalProfile;
use App\Modules\Fiscal\Services\DisabledFiscalProvider;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_area_is_permission_protected_and_disabled_by_default(): void
    {
        $clinic = $this->clinic('Clínica Fiscal', '00000000000901');
        $authorized = $this->userForClinic($clinic, true);
        $unauthorized = $this->userForClinic($clinic, false);

        $this->actingAs($unauthorized)->get(route('fiscal.index'))->assertForbidden();

        $this->actingAs($authorized)
            ->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Módulo fiscal')
            ->assertSee('somente homologação está disponível', false)
            ->assertSee('Desativado');

        $this->assertDatabaseCount('fiscal_profiles', 0);
    }

    public function test_manager_can_save_only_a_homologation_profile(): void
    {
        $clinic = $this->clinic('Clínica Homologação', '00000000000902');
        $user = $this->userForClinic($clinic, true);

        $this->actingAs($user)->put(route('fiscal.update'), $this->profilePayload())
            ->assertRedirect(route('fiscal.index'))
            ->assertSessionDoesntHaveErrors();

        $profile = FiscalProfile::query()->firstOrFail();
        $this->assertTrue($profile->enabled);
        $this->assertTrue($profile->nfce_enabled);
        $this->assertTrue($profile->nfse_enabled);
        $this->assertSame('homologation', $profile->environment);
        $this->assertNull($profile->provider);
        $this->assertSame('3304557', $profile->municipality_code);

        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.profile.updated',
        ]);

        $this->put(route('fiscal.update'), $this->profilePayload(['environment' => 'production']))
            ->assertSessionHasErrors('environment');

        $this->assertSame('homologation', $profile->fresh()->environment);
    }

    public function test_manager_can_select_focus_without_storing_a_secret_or_enabling_traffic(): void
    {
        $clinic = $this->clinic('Clínica Focus', '00000000000911');
        $user = $this->userForClinic($clinic, true);

        $this->actingAs($user)->put(route('fiscal.update'), $this->profilePayload([
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'provider_credential_reference' => 'vault://vetflow/focus/niteroi',
            'municipality_code' => '3303302',
            'nfse_dps_series' => '1',
            'nfse_dps_next_number' => '500',
            'simple_national_option' => '3',
            'simple_national_tax_regime' => '1',
            'special_tax_regime' => '0',
        ]))
            ->assertRedirect(route('fiscal.index'))
            ->assertSessionDoesntHaveErrors();

        $profile = FiscalProfile::query()->firstOrFail();
        $this->assertSame(FiscalProviderCatalog::FOCUS_NFE, $profile->provider);
        $this->assertSame('vault://vetflow/focus/niteroi', data_get($profile->metadata, 'provider_credential_reference'));
        $this->assertSame('1', data_get($profile->metadata, 'focus_nfe.dps_series'));
        $this->assertSame(500, FiscalDpsSequence::query()->firstOrFail()->next_number);
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.dps-sequence.configured',
        ]);
        $this->assertStringNotContainsString('token', json_encode($profile->toArray(), JSON_THROW_ON_ERROR));
        $this->assertInstanceOf(DisabledFiscalProvider::class, app(FiscalProvider::class));

        $this->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Focus NFe selecionada')
            ->assertSee('cliente HTTP, as credenciais reais e toda transmissão continuam desabilitados', false);
    }

    public function test_completed_sale_is_prepared_manually_with_frozen_snapshots_and_no_submission(): void
    {
        $clinic = $this->clinic('Clínica Snapshot', '00000000000903');
        $user = $this->userForClinic($clinic, true);
        $sale = $this->completedSale($clinic, $user);
        $this->profile($clinic, [
            'metadata' => ['provider_credential_reference' => 'vault://vetflow/focus/snapshot'],
        ]);

        $response = $this->actingAs($user)->post(route('fiscal.prepare', $sale->id), ['type' => 'nfce']);

        $document = FiscalDocument::query()->firstOrFail();
        $response->assertRedirect(route('fiscal.documents.show', $document->id));

        $this->assertSame('draft', $document->status);
        $this->assertSame('homologation', $document->environment);
        $this->assertNull($document->provider);
        $this->assertNull($document->submitted_at);
        $this->assertNull($document->access_key);
        $this->assertSame('Clínica Snapshot', $document->issuer_snapshot['corporate_name']);
        $this->assertSame('vault://vetflow/focus/snapshot', $document->issuer_snapshot['provider_credential_reference']);
        $this->assertSame('Consulta avulsa', $document->items_snapshot[0]['description']);
        $this->assertSame('30049099', $document->items_snapshot[0]['fiscal']['ncm']);
        $this->assertSame(3, $document->schema_version);
        $this->assertSame('150.00', $document->totals_snapshot['total']);
        $this->assertDatabaseHas('fiscal_document_events', [
            'fiscal_document_id' => $document->id,
            'clinic_id' => $clinic->id,
            'event' => 'prepared',
            'to_status' => 'draft',
        ]);

        $this->get(route('fiscal.documents.show', $document->id))
            ->assertOk()
            ->assertSee('Sem transmissão externa')
            ->assertSee('Consulta avulsa')
            ->assertSee('R$ 150,00');
    }

    public function test_preparation_is_idempotent_and_sale_completion_never_creates_it_automatically(): void
    {
        $clinic = $this->clinic('Clínica Idempotente', '00000000000904');
        $user = $this->userForClinic($clinic, true);
        $this->actingAs($user);
        $sale = $this->completedSale($clinic, $user);

        $this->assertDatabaseCount('fiscal_documents', 0);

        $this->profile($clinic);
        $this->post(route('fiscal.prepare', $sale->id), ['type' => 'nfce'])->assertSessionDoesntHaveErrors();
        $this->post(route('fiscal.prepare', $sale->id), ['type' => 'nfce'])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('fiscal_documents', 1);
        $this->assertDatabaseCount('fiscal_document_events', 1);
    }

    public function test_draft_sale_disabled_type_and_other_clinic_document_are_blocked(): void
    {
        $clinicA = $this->clinic('Clínica A', '00000000000905');
        $clinicB = $this->clinic('Clínica B', '00000000000906');
        $userA = $this->userForClinic($clinicA, true);
        $userB = $this->userForClinic($clinicB, true);
        $this->actingAs($userA);

        $draft = $this->sale($clinicA, $userA, 'draft');
        $this->profile($clinicA, ['nfse_enabled' => false]);

        $this->from(route('sales.edit', $draft->id))
            ->post(route('fiscal.prepare', $draft->id), ['type' => 'nfce'])
            ->assertSessionHasErrors('sale');

        $completed = $this->completedSale($clinicA, $userA);
        $this->post(route('fiscal.prepare', $completed->id), ['type' => 'nfse'])
            ->assertSessionHasErrors('type');

        $this->post(route('fiscal.prepare', $completed->id), ['type' => 'nfce'])
            ->assertSessionDoesntHaveErrors();
        $document = FiscalDocument::query()->firstOrFail();

        $this->actingAs($userB)->get(route('fiscal.documents.show', $document->id))->assertNotFound();
        $this->post(route('fiscal.prepare', $completed->id), ['type' => 'nfce'])->assertNotFound();
    }

    public function test_preparation_is_blocked_until_every_product_has_minimum_tax_data(): void
    {
        $clinic = $this->clinic('Clínica Pendente', '00000000000907');
        $user = $this->userForClinic($clinic, true);
        $this->actingAs($user);
        $sale = $this->completedSale($clinic, $user, false);
        $this->profile($clinic);

        $this->from(route('fiscal.index'))
            ->post(route('fiscal.prepare', $sale->id), ['type' => 'nfce'])
            ->assertSessionHasErrors('fiscal_items');

        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_mixed_sale_is_reviewed_allocated_and_frozen_in_exact_cents(): void
    {
        $clinic = $this->clinic('Clínica Mista', '00000000000908');
        $user = $this->userForClinic($clinic, true);
        $this->actingAs($user);
        $sale = $this->completedSale($clinic, $user);
        $service = PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Banho',
            'base_price' => 50,
            'active' => true,
        ]);
        ServiceFiscalProfile::query()->create([
            'clinic_id' => $clinic->id,
            'petshop_service_id' => $service->id,
            'national_taxation_code' => '050101',
            'service_list_item' => '05.01',
            'cnae' => '7500100',
            'municipality_code' => '3303302',
            'iss_rate' => '2.0000',
            'iss_withheld' => false,
            'iss_taxation' => '1',
            'ibs_cbs_tax_status' => '000',
            'ibs_cbs_classification_code' => '000001',
            'ready' => true,
            'missing_fields' => [],
            'configuration_version' => 1,
        ]);
        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'petshop_service_id' => $service->id,
            'type' => 'service',
            'description' => 'Banho',
            'quantity' => 1,
            'unit_price' => 50,
            'discount_total' => 0,
            'gross_total' => 50,
            'net_total' => 50,
            'total' => 50,
        ]);
        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 120,
            'installments' => 1,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'method' => 'pix',
            'amount' => 80,
            'installments' => 1,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $sale->update([
            'subtotal' => 200,
            'discount_total' => 10,
            'additions_total' => 4,
            'delivery_fee' => 6,
            'total' => 200,
            'paid_total' => 200,
        ]);
        $this->profile($clinic, ['municipality_code' => '3303302']);

        $this->post(route('fiscal.prepare', $sale->id), ['type' => 'nfce'])
            ->assertSessionDoesntHaveErrors();

        $nfce = FiscalDocument::query()->firstOrFail();
        $this->assertCount(1, $nfce->items_snapshot);
        $this->assertSame('product', $nfce->items_snapshot[0]['type']);
        $this->assertSame('yes', $nfce->totals_snapshot['requires_allocation']);
        $this->assertSame('150.00', $nfce->totals_snapshot['selected_items_total']);
        $this->assertSame('200.00', $nfce->totals_snapshot['sale_total']);

        $this->get(route('fiscal.documents.show', $nfce->id))
            ->assertOk()
            ->assertSee('Prepare também NFS-e');

        $this->post(route('fiscal.prepare', $sale->id), ['type' => 'nfse'])
            ->assertSessionDoesntHaveErrors();
        $nfse = FiscalDocument::query()->where('type', 'nfse')->firstOrFail();

        $this->get(route('fiscal.documents.show', $nfce->id))
            ->assertOk()
            ->assertSee('Rateio fiscal da venda')
            ->assertSee('R$ 151,50')
            ->assertSee('R$ 48,50')
            ->assertSee('Aprovar e congelar o rateio');

        $this->post(route('fiscal.sales.allocation.approve', $sale->id), [])
            ->assertSessionHasErrors('confirm_allocation');
        $this->post(route('fiscal.sales.allocation.approve', $sale->id), ['confirm_allocation' => '1'])
            ->assertRedirect(route('fiscal.documents.show', $nfce->id))
            ->assertSessionDoesntHaveErrors();

        $allocation = FiscalSaleAllocation::query()->firstOrFail();
        $nfce->refresh();
        $nfse->refresh();
        $this->assertSame($allocation->id, $nfce->fiscal_sale_allocation_id);
        $this->assertSame($allocation->id, $nfse->fiscal_sale_allocation_id);
        $this->assertSame('no', $nfce->totals_snapshot['requires_allocation']);
        $this->assertSame('no', $nfse->totals_snapshot['requires_allocation']);
        $this->assertSame('151.50', $nfce->totals_snapshot['total']);
        $this->assertSame('48.50', $nfse->totals_snapshot['total']);
        $this->assertSame('6.00', $nfce->totals_snapshot['delivery_fee']);
        $this->assertSame('0.00', $nfse->totals_snapshot['delivery_fee']);
        $this->assertSame('7.50', $nfce->items_snapshot[0]['allocated_sale_discount']);
        $this->assertSame('2.50', $nfse->items_snapshot[0]['allocated_sale_discount']);
        $this->assertSame('90.90', $nfce->payments_snapshot[0]['amount']);
        $this->assertSame('29.10', $nfse->payments_snapshot[0]['amount']);
        $this->assertDatabaseCount('fiscal_sale_allocations', 1);
        $this->assertDatabaseCount('fiscal_document_events', 4);

        $sale->update(['total' => 999]);
        $this->post(route('fiscal.sales.allocation.approve', $sale->id), ['confirm_allocation' => '1'])
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('fiscal_sale_allocations', 1);
        $this->assertSame('151.50', $nfce->fresh()->totals_snapshot['total']);

        $this->get(route('sales.receipt', $sale->id))
            ->assertOk()
            ->assertSee('DOCUMENTO SEM VALOR FISCAL')
            ->assertSee('NFCE')
            ->assertSee('NFSE')
            ->assertSee('Ver preparação');

        $otherClinic = $this->clinic('Clínica Sem Acesso', '00000000000918');
        $otherUser = $this->userForClinic($otherClinic, true);
        $this->actingAs($otherUser)
            ->post(route('fiscal.sales.allocation.approve', $sale->id), ['confirm_allocation' => '1'])
            ->assertNotFound();
    }

    public function test_standard_fiscal_roles_receive_access(): void
    {
        $this->seed(AuthorizationSeeder::class);

        foreach (['administrador', 'caixa', 'financeiro'] as $slug) {
            $this->assertTrue(
                Role::query()->where('slug', $slug)->firstOrFail()
                    ->permissions()->where('permissions.slug', 'fiscal.manage')->exists(),
                "O perfil {$slug} deve receber fiscal.manage."
            );
        }
    }

    public function test_niteroi_address_prefills_the_reviewed_national_nfse_route(): void
    {
        $clinic = $this->clinic('Clínica Niterói', '00000000000909');
        $clinic->update(['city' => 'Niterói', 'state' => 'RJ']);
        $user = $this->userForClinic($clinic, true);

        $this->actingAs($user)
            ->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Niterói/RJ')
            ->assertSee('3303302')
            ->assertSee('Emissor Nacional')
            ->assertSee('Produção restrita da NFS-e Nacional');

        $this->assertDatabaseCount('fiscal_profiles', 0);
    }

    public function test_niteroi_nfse_requires_the_same_service_subitem_and_iss_rate(): void
    {
        $clinic = $this->clinic('Clínica Niterói Regras', '00000000000910');
        $user = $this->userForClinic($clinic, true);
        $sale = $this->completedSale($clinic, $user);
        $this->profile($clinic, ['municipality_code' => '3303302']);

        $firstService = $this->serviceItem($sale, $clinic, 'Consulta veterinária', '05.01', '2.0000');
        $secondService = $this->serviceItem($sale, $clinic, 'Banho terapêutico', '06.01', '3.0000');
        $sale->update(['subtotal' => 250, 'total' => 250, 'paid_total' => 250]);

        $this->actingAs($user)
            ->from(route('sales.receipt', $sale->id))
            ->post(route('fiscal.prepare', $sale->id), ['type' => 'nfse'])
            ->assertSessionHasErrors('fiscal_items');

        $this->assertDatabaseMissing('fiscal_documents', ['type' => 'nfse']);

        ServiceFiscalProfile::query()->where('petshop_service_id', $secondService->id)->update([
            'service_list_item' => '05.01',
            'iss_rate' => '2.0000',
        ]);

        $this->post(route('fiscal.prepare', $sale->id), ['type' => 'nfse'])
            ->assertSessionDoesntHaveErrors();

        $document = FiscalDocument::query()->where('type', 'nfse')->firstOrFail();
        $this->assertCount(2, $document->items_snapshot);
        $this->assertSame('05.01', $document->items_snapshot[0]['fiscal']['service_list_item']);
        $this->assertSame('05.01', $document->items_snapshot[1]['fiscal']['service_list_item']);
        $this->assertNotSame($firstService->id, $secondService->id);
    }

    /** @param array<string, mixed> $overrides */
    private function profilePayload(array $overrides = []): array
    {
        return array_replace([
            'enabled' => '1',
            'environment' => 'homologation',
            'nfce_enabled' => '1',
            'nfse_enabled' => '1',
            'state_registration' => 'ISENTO',
            'municipal_registration' => '12345',
            'tax_regime' => 'simple_national',
            'municipality_code' => '3304557',
            'cnae' => '7500100',
            'certificate_reference' => 'cofre/certificado-clinica',
            'certificate_fingerprint' => 'AA:BB:CC',
            'certificate_expires_at' => now()->addYear()->toDateString(),
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function profile(Clinic $clinic, array $overrides = []): FiscalProfile
    {
        return FiscalProfile::query()->create(array_replace([
            'clinic_id' => $clinic->id,
            'enabled' => true,
            'environment' => 'homologation',
            'provider' => null,
            'nfce_enabled' => true,
            'nfse_enabled' => true,
            'configuration_version' => 1,
        ], $overrides));
    }

    private function completedSale(Clinic $clinic, User $user, bool $taxReady = true): Sale
    {
        return $this->sale($clinic, $user, 'completed', $taxReady);
    }

    private function serviceItem(Sale $sale, Clinic $clinic, string $name, string $serviceListItem, string $issRate): PetShopService
    {
        $service = PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'base_price' => 50,
            'active' => true,
        ]);

        ServiceFiscalProfile::query()->create([
            'clinic_id' => $clinic->id,
            'petshop_service_id' => $service->id,
            'national_taxation_code' => '050101',
            'service_list_item' => $serviceListItem,
            'cnae' => '7500100',
            'municipality_code' => '3303302',
            'iss_rate' => $issRate,
            'iss_withheld' => false,
            'iss_taxation' => '1',
            'ibs_cbs_tax_status' => '000',
            'ibs_cbs_classification_code' => '000001',
            'ready' => true,
            'missing_fields' => [],
            'configuration_version' => 1,
        ]);

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'petshop_service_id' => $service->id,
            'type' => 'service',
            'description' => $name,
            'quantity' => 1,
            'unit_price' => 50,
            'discount_total' => 0,
            'gross_total' => 50,
            'net_total' => 50,
            'total' => 50,
        ]);

        return $service;
    }

    private function sale(Clinic $clinic, User $user, string $status, bool $taxReady = true): Sale
    {
        $sale = Sale::query()->create([
            'clinic_id' => $clinic->id,
            'seller_user_id' => $user->id,
            'code' => 'VEN-'.Str::upper(Str::random(8)),
            'status' => $status,
            'payment_status' => $status === 'completed' ? 'paid' : 'pending',
            'sold_at' => now(),
            'completed_at' => $status === 'completed' ? now() : null,
            'subtotal' => 150,
            'discount_total' => 0,
            'additions_total' => 0,
            'delivery_fee' => 0,
            'total' => 150,
            'paid_total' => $status === 'completed' ? 150 : 0,
            'change_total' => 0,
            'stock_applied' => false,
            'financial_applied' => false,
        ]);

        $product = Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto fiscal de teste',
            'sale_price' => 150,
            'active' => true,
        ]);

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'type' => 'product',
            'description' => 'Consulta avulsa',
            'quantity' => 1,
            'unit_price' => 150,
            'discount_total' => 0,
            'gross_total' => 150,
            'net_total' => 150,
            'total' => 150,
        ]);

        if ($taxReady) {
            ProductFiscalProfile::query()->create([
                'clinic_id' => $clinic->id,
                'product_id' => $product->id,
                'ncm' => '30049099',
                'origin_code' => '0',
                'default_cfop' => '5102',
                'icms_code' => '102',
                'pis_code' => '49',
                'cofins_code' => '49',
                'fiscal_unit' => 'UN',
                'ready' => true,
                'missing_fields' => [],
                'configuration_version' => 1,
            ]);
        }

        return $sale;
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

    private function userForClinic(Clinic $clinic, bool $authorized): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);

        if (! $authorized) {
            return $user;
        }

        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            [
                'name' => 'Gerenciar módulo fiscal',
                'description' => 'Permissão fiscal de teste.',
                'group' => 'Fiscal',
                'active' => true,
            ]
        );
        $salesPermission = Permission::query()->firstOrCreate(
            ['slug' => 'sales.manage'],
            [
                'name' => 'Gerenciar vendas',
                'description' => 'Permissão de vendas para o teste fiscal.',
                'group' => 'Vendas',
                'active' => true,
            ]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal '.Str::random(6),
            'slug' => 'fiscal-'.Str::lower(Str::random(8)),
            'description' => 'Perfil fiscal de teste',
            'system' => false,
            'active' => true,
        ]);
        $role->permissions()->attach([$permission->id, $salesPermission->id]);

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
