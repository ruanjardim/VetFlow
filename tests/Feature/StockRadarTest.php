<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockRadarTest extends TestCase
{
    use RefreshDatabase;

    public function test_radar_classifies_active_products_with_transparent_rules_and_clinic_isolation(): void
    {
        $clinic = $this->clinic('Clinica Radar A', '31000000000101');
        $otherClinic = $this->clinic('Clinica Radar B', '31000000000102');
        $user = $this->userForClinic($clinic, ['inventory.manage', 'products.manage', 'purchase-entries.manage']);

        $replenish = $this->product($clinic, 'Produto para repor', 1, 5, 10);
        $stalled = $this->oldProduct($clinic, 'Produto sem saida', 20, 5, 10);
        $highCoverage = $this->oldProduct($clinic, 'Produto com cobertura alta', 100, 5, 2);
        $new = $this->product($clinic, 'Produto novo', 20, 5, 5);
        $adequate = $this->oldProduct($clinic, 'Produto adequado', 10, 5, 4);
        $unparameterized = $this->oldProduct($clinic, 'Produto sem minimo', 0, 0, 9);

        $inactive = $this->oldProduct($clinic, 'Produto inativo', 50, 5, 10, false);
        $external = $this->oldProduct($otherClinic, 'Produto externo', 80, 5, 10);

        $this->recordDemand($clinic, [
            [$highCoverage, 10, 0],
            [$adequate, 50, 5],
        ]);

        $stockBefore = DB::table('products')
            ->whereIn('id', [$replenish->id, $stalled->id, $highCoverage->id, $new->id, $adequate->id, $unparameterized->id])
            ->pluck('stock_quantity', 'id')
            ->all();

        $response = $this->actingAs($user)->get(route('inventory-movements.radar'));

        $response
            ->assertOk()
            ->assertSee('Radar de estoque')
            ->assertSee('Leitura observacional')
            ->assertSee('Produto para repor')
            ->assertSee('Produto sem saida')
            ->assertSee('Produto com cobertura alta')
            ->assertSee('Produto novo')
            ->assertSee('Produto adequado')
            ->assertSee('Produto sem minimo')
            ->assertDontSee('Produto inativo')
            ->assertDontSee('Produto externo');

        $stats = $response->viewData('stats');
        $this->assertSame(6, $stats['total']);
        $this->assertEquals(550.0, (float) $stats['stock_value']);
        $this->assertSame(1, $stats['categories']['replenish']['count']);
        $this->assertSame(1, $stats['categories']['stalled']['count']);
        $this->assertSame(1, $stats['categories']['high_coverage']['count']);
        $this->assertSame(1, $stats['categories']['new']['count']);
        $this->assertSame(1, $stats['categories']['adequate']['count']);
        $this->assertSame(1, $stats['categories']['unparameterized']['count']);

        /** @var LengthAwarePaginator $items */
        $items = $response->viewData('items');
        $itemsByProduct = $items->getCollection()->keyBy(
            fn (array $item): string => $item['product']->name
        );
        $categories = $items->getCollection()->mapWithKeys(
            fn (array $item): array => [$item['product']->name => $item['category']]
        );

        $this->assertSame('replenish', $categories['Produto para repor']);
        $this->assertSame('stalled', $categories['Produto sem saida']);
        $this->assertSame('high_coverage', $categories['Produto com cobertura alta']);
        $this->assertSame('new', $categories['Produto novo']);
        $this->assertSame('adequate', $categories['Produto adequado']);
        $this->assertSame('unparameterized', $categories['Produto sem minimo']);
        $this->assertSame(45.0, (float) $itemsByProduct['Produto adequado']['net_demand']);

        $this->assertSame(
            $stockBefore,
            DB::table('products')
                ->whereIn('id', array_keys($stockBefore))
                ->pluck('stock_quantity', 'id')
                ->all()
        );
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(50.0, (float) $inactive->fresh()->stock_quantity);
        $this->assertSame(80.0, (float) $external->fresh()->stock_quantity);
    }

    public function test_radar_filters_by_signal_search_product_category_and_brand(): void
    {
        $clinic = $this->clinic('Clinica Filtros Radar', '32000000000101');
        $user = $this->userForClinic($clinic, ['inventory.manage']);

        $this->oldProduct($clinic, 'Shampoo parado', 12, 2, 5, true, 'Higiene', 'Marca Azul');
        $this->oldProduct($clinic, 'Racao parada', 8, 2, 7, true, 'Alimentos', 'Marca Verde');
        $this->oldProduct($clinic, 'Vacina critica', 1, 5, 20, true, 'Farmacia', 'Marca Azul');

        $response = $this->actingAs($user)->get(route('inventory-movements.radar', [
            'category' => 'stalled',
            'q' => 'shampoo',
            'product_category' => 'Higiene',
            'brand' => 'Marca Azul',
        ]));

        $response
            ->assertOk()
            ->assertSee('Shampoo parado')
            ->assertDontSee('Racao parada')
            ->assertDontSee('Vacina critica')
            ->assertViewHas('items', fn (LengthAwarePaginator $items): bool => $items->total() === 1)
            ->assertViewHas('filters', fn (array $filters): bool => $filters['category'] === 'stalled'
                && $filters['q'] === 'shampoo'
                && $filters['product_category'] === 'Higiene'
                && $filters['brand'] === 'Marca Azul');
    }

    public function test_radar_requires_inventory_permission(): void
    {
        $clinic = $this->clinic('Clinica Sem Radar', '33000000000101');
        $user = $this->userForClinic($clinic, []);

        $this->actingAs($user)
            ->get(route('inventory-movements.radar'))
            ->assertForbidden();
    }

    private function recordDemand(Clinic $clinic, array $items): void
    {
        $sale = Sale::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'RADAR-'.Str::upper(Str::random(8)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'sold_at' => now(),
            'completed_at' => now(),
        ]);

        foreach ($items as [$product, $quantity, $returnedQuantity]) {
            SaleItem::query()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'type' => 'product',
                'description' => $product->name,
                'quantity' => $quantity,
                'returned_quantity' => $returnedQuantity,
                'unit_price' => 10,
                'total' => $quantity * 10,
            ]);
        }
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

    private function oldProduct(
        Clinic $clinic,
        string $name,
        float $stock,
        float $minimumStock,
        float $costPrice,
        bool $active = true,
        ?string $category = null,
        ?string $brand = null,
    ): Product {
        $product = $this->product($clinic, $name, $stock, $minimumStock, $costPrice, $active, $category, $brand);

        DB::table('products')->where('id', $product->id)->update([
            'created_at' => now()->subDays(60),
        ]);

        return $product->refresh();
    }

    private function product(
        Clinic $clinic,
        string $name,
        float $stock,
        float $minimumStock,
        float $costPrice,
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
            'cost_price' => $costPrice,
            'stock_quantity' => $stock,
            'minimum_stock' => $minimumStock,
            'active' => $active,
        ]);
    }

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Radar test role '.Str::random(6),
            'slug' => 'radar-test-role-'.Str::lower(Str::random(8)),
            'description' => 'Role para testes do radar',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Permissao para teste',
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
