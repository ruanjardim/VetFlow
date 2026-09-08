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

class ProductAbcAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_curve_classifies_return_adjusted_product_revenue_and_isolates_clinics(): void
    {
        $clinic = $this->clinic('Clínica Curva A', '41000000000101');
        $otherClinic = $this->clinic('Clínica Curva B', '41000000000102');
        $user = $this->userForClinic($clinic, ['sales.manage', 'products.manage', 'inventory.manage']);

        $productA = $this->product($clinic, 'Produto A dominante', 'Farmácia', 5, 10);
        $productB = $this->product($clinic, 'Produto B intermediário', 'Higiene', 4, 20);
        $productC = $this->product($clinic, 'Produto C complementar', 'Acessórios', 3, 30);
        $external = $this->product($otherClinic, 'Produto externo', 'Farmácia', 20, 50);

        $this->sale($clinic, [
            [$productA, 10, 100, 2, 200],
            [$productB, 3, 50, 0, 0],
            [$productC, 1, 50, 0, 0],
        ]);
        $this->sale($otherClinic, [[$external, 20, 100, 0, 0]]);
        $this->sale($clinic, [[$productC, 20, 100, 0, 0]], now()->subDays(100));

        $response = $this->actingAs($user)->get(route('sales.product-abc'));

        $response
            ->assertOk()
            ->assertSee('Curva ABC de produtos')
            ->assertSee('Produto A dominante')
            ->assertSee('Produto B intermediário')
            ->assertSee('Produto C complementar')
            ->assertDontSee('Produto externo');

        $stats = $response->viewData('stats');
        $this->assertSame(3, $stats['total_products']);
        $this->assertSame(1, $stats['sales_count']);
        $this->assertSame(12.0, (float) $stats['net_quantity']);
        $this->assertSame(1000.0, (float) $stats['net_revenue']);
        $this->assertSame(200.0, (float) $stats['returns']);
        $this->assertSame(220.0, (float) $stats['stock_value']);
        $this->assertSame(1, $stats['classes']['A']['count']);
        $this->assertSame(1, $stats['classes']['B']['count']);
        $this->assertSame(1, $stats['classes']['C']['count']);

        /** @var LengthAwarePaginator $items */
        $items = $response->viewData('items');
        $itemsByName = $items->getCollection()->keyBy('description');

        $this->assertSame('A', $itemsByName['Produto A dominante']['abc_class']);
        $this->assertSame(800.0, (float) $itemsByName['Produto A dominante']['net_revenue']);
        $this->assertSame(80.0, (float) $itemsByName['Produto A dominante']['participation_percent']);
        $this->assertSame('B', $itemsByName['Produto B intermediário']['abc_class']);
        $this->assertSame(95.0, (float) $itemsByName['Produto B intermediário']['cumulative_percent']);
        $this->assertSame('C', $itemsByName['Produto C complementar']['abc_class']);
        $this->assertSame(100.0, (float) $itemsByName['Produto C complementar']['cumulative_percent']);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_curve_filters_without_recalculating_the_original_classification(): void
    {
        $clinic = $this->clinic('Clínica Filtro ABC', '42000000000101');
        $user = $this->userForClinic($clinic, ['sales.manage']);
        $productA = $this->product($clinic, 'Ração líder', 'Alimentos', 10, 20);
        $productB = $this->product($clinic, 'Shampoo intermediário', 'Higiene', 8, 10);
        $productC = $this->product($clinic, 'Brinquedo complementar', 'Acessórios', 5, 5);

        $this->sale($clinic, [
            [$productA, 8, 100, 0, 0],
            [$productB, 3, 50, 0, 0],
            [$productC, 1, 50, 0, 0],
        ]);

        $response = $this->actingAs($user)->get(route('sales.product-abc', [
            'period' => '30',
            'class' => 'B',
            'category' => 'Higiene',
            'q' => 'intermediário',
        ]));

        $response
            ->assertOk()
            ->assertSee('Shampoo intermediário')
            ->assertDontSee('Ração líder')
            ->assertDontSee('Brinquedo complementar')
            ->assertViewHas('items', fn (LengthAwarePaginator $items): bool => $items->total() === 1
                && $items->first()['abc_class'] === 'B')
            ->assertViewHas('period', fn (array $period): bool => $period['value'] === '30');

        $stats = $response->viewData('stats');
        $this->assertSame(3, $stats['total_products']);
        $this->assertSame(1, $stats['classes']['B']['count']);
    }

    public function test_curve_requires_sales_permission(): void
    {
        $clinic = $this->clinic('Clínica Sem Curva', '43000000000101');
        $user = $this->userForClinic($clinic, []);

        $this->actingAs($user)
            ->get(route('sales.product-abc'))
            ->assertForbidden();
    }

    /** @param array<int, array{0: Product, 1: float|int, 2: float|int, 3: float|int, 4: float|int}> $lines */
    private function sale(Clinic $clinic, array $lines, $soldAt = null): Sale
    {
        $soldAt ??= now();
        $total = collect($lines)->sum(fn (array $line): float => (float) $line[1] * (float) $line[2]);
        $sale = Sale::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'ABC-'.Str::upper(Str::random(8)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => $total,
            'total' => $total,
            'sold_at' => $soldAt,
            'completed_at' => $soldAt,
        ]);

        foreach ($lines as [$product, $quantity, $unitPrice, $returnedQuantity, $refundedTotal]) {
            $lineTotal = (float) $quantity * (float) $unitPrice;

            SaleItem::query()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'type' => 'product',
                'description' => $product->name,
                'product_name_snapshot' => $product->name,
                'category_snapshot' => $product->category,
                'quantity' => $quantity,
                'returned_quantity' => $returnedQuantity,
                'unit_price' => $unitPrice,
                'original_unit_price' => $unitPrice,
                'cost_unit_price' => $product->cost_price,
                'gross_total' => $lineTotal,
                'net_total' => $lineTotal,
                'total' => $lineTotal,
                'refunded_total' => $refundedTotal,
            ]);
        }

        return $sale;
    }

    private function product(
        Clinic $clinic,
        string $name,
        string $category,
        float $stock,
        float $cost,
    ): Product {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'category' => $category,
            'sku' => Str::upper(Str::random(8)),
            'cost_price' => $cost,
            'sale_price' => $cost * 2,
            'stock_quantity' => $stock,
            'minimum_stock' => 1,
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

    private function userForClinic(Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $role = Role::query()->create([
            'name' => 'ABC test role '.Str::random(6),
            'slug' => 'abc-test-role-'.Str::lower(Str::random(8)),
            'description' => 'Role para testes da curva ABC',
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
