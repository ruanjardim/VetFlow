<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\PdvScaleSetting;
use App\Modules\Sales\Services\PdvScaleLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\OpensCashSessions;
use Tests\TestCase;

class PdvScaleLabelsTest extends TestCase
{
    use OpensCashSessions;
    use RefreshDatabase;

    public function test_clinic_can_configure_scale_labels_with_an_audit_trail(): void
    {
        $clinic = $this->clinic('Pet Shop Balança', '00000000001001');
        $user = $this->user($clinic, ['sales.manage']);

        $this->actingAs($user)
            ->put(route('sales.pdv-scale-settings.update'), [
                'enabled' => '1',
                'label_prefix' => '20',
                'product_code_digits' => '5',
                'weight_decimals' => '3',
            ])
            ->assertRedirect(route('sales.pdv-scale-settings.edit'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('pdv_scale_settings', [
            'clinic_id' => $clinic->id,
            'enabled' => true,
            'label_prefix' => '20',
            'product_code_digits' => 5,
            'weight_decimals' => 3,
            'updated_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'pdv.scale-settings.updated',
        ]);

        $this->get(route('sales.pdv-scale-settings.edit'))
            ->assertOk()
            ->assertSee('Balança e etiquetas de peso')
            ->assertSee('Conexão serial direta');
    }

    public function test_valid_scale_label_adds_fractional_weight_from_the_current_clinic(): void
    {
        $clinic = $this->clinic('Pet Shop Peso Certo', '00000000001002');
        $user = $this->user($clinic, ['sales.manage']);
        $setting = $this->setting($clinic);
        $product = $this->product($clinic, 'Biscoito a granel', '12345', 10, 39.90);
        $label = app(PdvScaleLabelService::class)->exampleLabel($setting, '12345', 0.725);
        $this->openCashSession($user, $clinic);

        $this->actingAs($user)
            ->getJson(route('sales.product-lookup', ['gtin' => $label]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('mode', 'scale_label')
            ->assertJsonPath('item.product_id', $product->id)
            ->assertJsonPath('item.scale_code', '12345')
            ->assertJsonPath('item.quantity', 0.725)
            ->assertJsonPath('item.unit', 'kg')
            ->assertJsonPath('item.scale_label', true);

        $this->post(route('sales.store'), [
            'pdv_checkout' => '1',
            'status' => 'completed',
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => '0.725',
                'unit_price' => '39.90',
            ]],
            'payments' => [[
                'method' => 'pix',
                'amount' => '28.93',
            ]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('9.275', $product->refresh()->stock_quantity);
    }

    public function test_scale_label_fails_closed_for_checksum_unknown_product_and_other_tenant(): void
    {
        $clinicA = $this->clinic('Pet Shop A', '00000000001003');
        $clinicB = $this->clinic('Pet Shop B', '00000000001004');
        $settingA = $this->setting($clinicA);
        $this->setting($clinicB);
        $productA = $this->product($clinicA, 'Produto A', '00077', 5, 20);
        $this->product($clinicB, 'Produto B', '00077', 5, 30);
        $user = $this->user($clinicA, ['sales.manage']);
        $label = app(PdvScaleLabelService::class)->exampleLabel($settingA, '00077', 1.250);

        $this->actingAs($user)
            ->getJson(route('sales.product-lookup', ['gtin' => $label]))
            ->assertOk()
            ->assertJsonPath('item.product_id', $productA->id);

        $invalid = substr($label, 0, 12).(((int) $label[12] + 1) % 10);
        $this->getJson(route('sales.product-lookup', ['gtin' => $invalid]))
            ->assertUnprocessable()
            ->assertJsonPath('manual_allowed', false)
            ->assertJsonPath('mode', 'scale_label');

        $unknown = app(PdvScaleLabelService::class)->exampleLabel($settingA, '99999', 0.500);
        $this->getJson(route('sales.product-lookup', ['gtin' => $unknown]))
            ->assertUnprocessable()
            ->assertJsonPath('manual_allowed', false)
            ->assertJsonPath('message', 'Nenhum produto ativo usa o código de balança 99999 nesta clínica.');
    }

    public function test_scale_code_requires_kilograms_and_is_unique_inside_the_clinic(): void
    {
        $clinic = $this->clinic('Pet Shop Cadastro Peso', '00000000001005');
        $user = $this->user($clinic, ['products.manage']);
        $this->product($clinic, 'Produto existente', '00420', 5, 20);

        $payload = [
            'name' => 'Produto novo',
            'scale_code' => '00420',
            'unit' => 'un',
            'cost_price' => '10',
            'sale_price' => '20',
            'stock_quantity' => '5',
            'minimum_stock' => '0',
            'active' => '1',
        ];

        $this->actingAs($user)
            ->post(route('products.store'), $payload)
            ->assertSessionHasErrors(['scale_code', 'unit']);

        $this->post(route('products.store'), array_replace($payload, [
            'scale_code' => '00421',
            'unit' => 'kg',
        ]))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('products', [
            'clinic_id' => $clinic->id,
            'scale_code' => '00421',
            'unit' => 'kg',
        ]);
    }

    public function test_offline_catalog_carries_only_the_scale_layout_and_product_codes(): void
    {
        $clinic = $this->clinic('Pet Shop Offline Peso', '00000000001006');
        $user = $this->user($clinic, ['sales.manage']);
        $this->setting($clinic);
        $product = $this->product($clinic, 'Ração por peso', '03100', 8, 16.50);

        $this->actingAs($user)
            ->getJson(route('sales.pdv.offline-catalog'))
            ->assertOk()
            ->assertJsonPath('scale.enabled', true)
            ->assertJsonPath('scale.label_prefix', '20')
            ->assertJsonPath('scale.product_code_digits', 5)
            ->assertJsonPath('scale.weight_decimals', 3)
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.scale_code', '03100')
            ->assertJsonPath('items.0.unit', 'kg')
            ->assertJsonMissingPath('scale.updated_by');
    }

    private function setting(Clinic $clinic): PdvScaleSetting
    {
        return PdvScaleSetting::query()->create([
            'clinic_id' => $clinic->id,
            'enabled' => true,
            'label_prefix' => '20',
            'product_code_digits' => 5,
            'weight_decimals' => 3,
        ]);
    }

    private function product(Clinic $clinic, string $name, string $scaleCode, float $stock, float $price): Product
    {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'scale_code' => $scaleCode,
            'unit' => 'kg',
            'cost_price' => $price / 2,
            'sale_price' => $price,
            'stock_quantity' => $stock,
            'minimum_stock' => 0,
            'active' => true,
        ]);
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

    /** @param array<int, string> $permissions */
    private function user(Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $role = Role::query()->create([
            'name' => 'Balança '.Str::random(6),
            'slug' => 'balanca-'.Str::lower(Str::random(8)),
            'description' => 'Perfil de teste da balança',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => 'Permissão de teste',
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
