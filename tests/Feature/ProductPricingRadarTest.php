<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductPricingRadarTest extends TestCase
{
    use RefreshDatabase;

    public function test_radar_classifies_active_products_and_calculates_margin_exposure_per_clinic(): void
    {
        $clinic = $this->clinic('Clínica Preços A', '51000000000101');
        $otherClinic = $this->clinic('Clínica Preços B', '51000000000102');
        $user = $this->userForClinic($clinic, ['products.manage']);

        $missingCost = $this->product($clinic, 'Produto sem custo', 0, 100, 2);
        $missingPrice = $this->product($clinic, 'Produto sem preço', 10, 0, 3);
        $belowCost = $this->product($clinic, 'Produto abaixo do custo', 20, 15, 4);
        $breakEven = $this->product($clinic, 'Produto sem margem', 20, 20, 5);
        $lowMargin = $this->product($clinic, 'Produto margem baixa', 90, 100, 6);
        $adequate = $this->product($clinic, 'Produto margem adequada', 50, 100, 7);
        $this->product($clinic, 'Produto inativo', 10, 30, 8, false);
        $this->product($otherClinic, 'Produto externo', 10, 30, 9);

        $response = $this->actingAs($user)->get(route('products.pricing-radar'));

        $response
            ->assertOk()
            ->assertSee('Radar de preços')
            ->assertSee('Produto sem custo')
            ->assertSee('Produto sem preço')
            ->assertSee('Produto abaixo do custo')
            ->assertSee('Produto sem margem')
            ->assertSee('Produto margem baixa')
            ->assertSee('Produto margem adequada')
            ->assertDontSee('Produto inativo')
            ->assertDontSee('Produto externo');

        $stats = $response->viewData('stats');
        $this->assertSame(6, $stats['total']);
        $this->assertSame(4, $stats['known_margin_products']);
        $this->assertSame(1100.0, (float) $stats['stock_value']);
        $this->assertSame(1660.0, (float) $stats['projected_revenue']);
        $this->assertSame(390.0, (float) $stats['projected_gross_profit']);

        foreach (array_keys($stats['signals']) as $signal) {
            $this->assertSame(1, $stats['signals'][$signal]['count']);
        }

        /** @var LengthAwarePaginator $items */
        $items = $response->viewData('items');
        $itemsByName = $items->getCollection()->keyBy(fn (array $item): string => $item['product']->name);

        $this->assertSame('missing_cost', $itemsByName[$missingCost->name]['signal']);
        $this->assertNull($itemsByName[$missingCost->name]['margin_percent']);
        $this->assertSame('missing_price', $itemsByName[$missingPrice->name]['signal']);
        $this->assertSame('below_cost', $itemsByName[$belowCost->name]['signal']);
        $this->assertSame(-33.33, (float) $itemsByName[$belowCost->name]['margin_percent']);
        $this->assertSame('break_even', $itemsByName[$breakEven->name]['signal']);
        $this->assertSame(0.0, (float) $itemsByName[$breakEven->name]['margin_percent']);
        $this->assertSame('low_margin', $itemsByName[$lowMargin->name]['signal']);
        $this->assertSame(10.0, (float) $itemsByName[$lowMargin->name]['margin_percent']);
        $this->assertSame('adequate', $itemsByName[$adequate->name]['signal']);
        $this->assertSame(50.0, (float) $itemsByName[$adequate->name]['margin_percent']);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_radar_filters_without_changing_consolidated_totals(): void
    {
        $clinic = $this->clinic('Clínica Filtros Preços', '52000000000101');
        $user = $this->userForClinic($clinic, ['products.manage']);

        $this->product($clinic, 'Shampoo margem curta', 90, 100, 4, true, 'Higiene', 'Marca Azul');
        $this->product($clinic, 'Ração margem curta', 90, 100, 5, true, 'Alimentos', 'Marca Verde');
        $this->product($clinic, 'Vacina saudável', 40, 100, 6, true, 'Farmácia', 'Marca Azul');

        $response = $this->actingAs($user)->get(route('products.pricing-radar', [
            'signal' => 'low_margin',
            'q' => 'shampoo',
            'category' => 'Higiene',
            'brand' => 'Marca Azul',
        ]));

        $response
            ->assertOk()
            ->assertSee('Shampoo margem curta')
            ->assertDontSee('Ração margem curta')
            ->assertDontSee('Vacina saudável')
            ->assertViewHas('items', fn (LengthAwarePaginator $items): bool => $items->total() === 1)
            ->assertViewHas('filters', fn (array $filters): bool => $filters['signal'] === 'low_margin'
                && $filters['q'] === 'shampoo'
                && $filters['category'] === 'Higiene'
                && $filters['brand'] === 'Marca Azul');

        $stats = $response->viewData('stats');
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['signals']['low_margin']['count']);
    }

    public function test_radar_requires_products_permission(): void
    {
        $clinic = $this->clinic('Clínica Sem Preços', '53000000000101');
        $user = $this->userForClinic($clinic, []);

        $this->actingAs($user)
            ->get(route('products.pricing-radar'))
            ->assertForbidden();
    }

    private function product(
        Clinic $clinic,
        string $name,
        float $cost,
        float $price,
        float $stock,
        bool $active = true,
        ?string $category = null,
        ?string $brand = null,
    ): Product {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'category' => $category,
            'brand' => $brand,
            'sku' => Str::upper(Str::random(8)),
            'cost_price' => $cost,
            'sale_price' => $price,
            'stock_quantity' => $stock,
            'minimum_stock' => 1,
            'active' => $active,
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

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Pricing test role '.Str::random(6),
            'slug' => 'pricing-test-role-'.Str::lower(Str::random(8)),
            'description' => 'Role para testes do radar de preços',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Permissão para teste',
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
