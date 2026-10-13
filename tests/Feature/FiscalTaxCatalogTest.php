<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\ProductFiscalProfile;
use App\Modules\Fiscal\Models\ServiceFiscalProfile;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalTaxCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_center_reports_pending_product_and_service_catalogs(): void
    {
        [$clinic, $user] = $this->context('Clínica Catálogo', '00000000000911');
        $this->product($clinic, 'Ração clínica');
        $this->service($clinic, 'Banho terapêutico');

        $this->actingAs($user)
            ->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Produtos prontos')
            ->assertSee('0/1')
            ->assertSee('Serviços prontos')
            ->assertSee('1 pendente(s)');

        $this->get(route('fiscal.products.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Ração clínica')
            ->assertSee('Pendente');
        $this->get(route('fiscal.services.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Banho terapêutico')
            ->assertSee('Pendente');
    }

    public function test_product_classification_tracks_missing_fields_then_becomes_ready(): void
    {
        [$clinic, $user] = $this->context('Clínica Produto', '00000000000912');
        $product = $this->product($clinic, 'Antipulgas');

        $this->actingAs($user)->put(route('fiscal.products.update', $product->id), [
            'ncm' => '3004.90.99',
            'origin_code' => '0',
        ])->assertRedirect(route('fiscal.products.index'));

        $profile = ProductFiscalProfile::query()->firstOrFail();
        $this->assertFalse($profile->ready);
        $this->assertSame('30049099', $profile->ncm);
        $this->assertContains('default_cfop', $profile->missing_fields);

        $this->put(route('fiscal.products.update', $product->id), $this->productPayload())
            ->assertRedirect(route('fiscal.products.index'))
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertTrue($profile->ready);
        $this->assertSame([], $profile->missing_fields);
        $this->assertSame(2, $profile->configuration_version);
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.product-profile.updated',
            'subject_id' => (string) $product->id,
        ]);

        $this->get(route('fiscal.products.index', ['status' => 'ready']))
            ->assertOk()
            ->assertSee('Antipulgas')
            ->assertSee('30049099')
            ->assertSee('5102');

        $this->put(route('fiscal.products.update', $product->id), $this->productPayload())
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(2, $profile->fresh()->configuration_version);
    }

    public function test_product_codes_are_validated_before_saving(): void
    {
        [$clinic, $user] = $this->context('Clínica Validação', '00000000000913');
        $product = $this->product($clinic, 'Produto inválido');

        $this->actingAs($user)
            ->from(route('fiscal.products.edit', $product->id))
            ->put(route('fiscal.products.update', $product->id), $this->productPayload([
                'ncm' => '123',
                'default_cfop' => '51',
                'icms_code' => 'A1',
            ]))
            ->assertSessionHasErrors(['ncm', 'default_cfop', 'icms_code']);

        $this->assertDatabaseCount('product_fiscal_profiles', 0);
    }

    public function test_service_classification_normalizes_iss_and_becomes_ready(): void
    {
        [$clinic, $user] = $this->context('Clínica Serviço', '00000000000914');
        $service = $this->service($clinic, 'Consulta veterinária');

        $this->actingAs($user)
            ->put(route('fiscal.services.update', $service->id), $this->servicePayload())
            ->assertRedirect(route('fiscal.services.index'))
            ->assertSessionHas('success');

        $profile = ServiceFiscalProfile::query()->firstOrFail();
        $this->assertTrue($profile->ready);
        $this->assertSame('7500100', $profile->cnae);
        $this->assertSame('3304557', $profile->municipality_code);
        $this->assertSame('2.5000', $profile->iss_rate);
        $this->assertFalse($profile->iss_withheld);
        $this->assertSame('1', $profile->iss_taxation);
        $this->assertSame('000', $profile->ibs_cbs_tax_status);
        $this->assertSame('000001', $profile->ibs_cbs_classification_code);

        $this->get(route('fiscal.services.edit', $service->id))
            ->assertOk()
            ->assertSee('Cadastro mínimo completo')
            ->assertSee('05.01');
    }

    public function test_tax_catalog_is_isolated_by_clinic(): void
    {
        [$clinicA, $userA] = $this->context('Clínica A Fiscal', '00000000000915');
        [$clinicB, $userB] = $this->context('Clínica B Fiscal', '00000000000916');
        $productA = $this->product($clinicA, 'Produto exclusivo A');
        $productB = $this->product($clinicB, 'Produto exclusivo B');

        $this->actingAs($userA)
            ->get(route('fiscal.products.index'))
            ->assertOk()
            ->assertSee('Produto exclusivo A')
            ->assertDontSee('Produto exclusivo B');
        $this->get(route('fiscal.products.edit', $productB->id))->assertNotFound();
        $this->put(route('fiscal.products.update', $productB->id), $this->productPayload())->assertNotFound();

        $this->actingAs($userB)->put(route('fiscal.products.update', $productB->id), $this->productPayload())
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('product_fiscal_profiles', [
            'clinic_id' => $clinicB->id,
            'product_id' => $productB->id,
            'ready' => true,
        ]);
        $this->assertDatabaseMissing('product_fiscal_profiles', ['product_id' => $productA->id]);
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
            'name' => 'Fiscal '.Str::random(6),
            'slug' => 'fiscal-'.Str::lower(Str::random(8)),
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

    private function product(Clinic $clinic, string $name): Product
    {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'unit' => 'un',
            'sale_price' => 100,
            'active' => true,
        ]);
    }

    private function service(Clinic $clinic, string $name): PetShopService
    {
        return PetShopService::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'base_price' => 100,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function productPayload(array $overrides = []): array
    {
        return array_replace([
            'ncm' => '3004.90.99',
            'cest' => '',
            'origin_code' => '0',
            'default_cfop' => '5102',
            'icms_code' => '102',
            'pis_code' => '49',
            'cofins_code' => '49',
            'fiscal_unit' => 'UN',
            'tax_benefit_code' => '',
            'ibs_cbs_tax_status' => '000',
            'ibs_cbs_classification_code' => '000001',
            'notes' => 'Confirmado para homologação.',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function servicePayload(): array
    {
        return [
            'national_taxation_code' => '050101',
            'municipal_taxation_code' => '0501',
            'service_list_item' => '05.01',
            'cnae' => '7500-1/00',
            'nbs_code' => '1.1403.90.00',
            'municipality_code' => '3304557',
            'iss_rate' => '2,50',
            'iss_withheld' => '0',
            'iss_taxation' => '1',
            'ibs_cbs_tax_status' => '000',
            'ibs_cbs_classification_code' => '000001',
            'operation_indicator' => '',
            'notes' => null,
        ];
    }
}
