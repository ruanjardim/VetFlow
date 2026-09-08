<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryVarianceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_calculates_accuracy_and_cost_impact_only_from_recent_finalized_counts_in_the_clinic(): void
    {
        $clinic = $this->clinic('Clínica Relatório A', '71000000000101');
        $otherClinic = $this->clinic('Clínica Relatório B', '71000000000102');
        $user = $this->userForClinic($clinic, ['inventory.manage']);
        $surplus = $this->product($clinic, 'Produto com sobra', 10, 'Farmácia');
        $shortage = $this->product($clinic, 'Produto com falta', 5, 'Farmácia');
        $matching = $this->product($clinic, 'Produto sem diferença', 9, 'Alimentos');
        $external = $this->product($otherClinic, 'Produto de outra clínica', 100, 'Farmácia');

        $this->createCount($clinic, $user, 'finalized', now()->subDays(10), [
            [$surplus, 10, 12, 2, 10],
            [$shortage, 8, 5, -3, 5],
            [$matching, 4, 4, 0, 9],
        ]);
        $this->createCount($clinic, $user, 'finalized', now()->subDays(120), [
            [$surplus, 3, 8, 5, 10],
        ]);
        $this->createCount($clinic, $user, 'draft', null, [
            [$surplus, 10, 18, 8, 10],
        ]);
        $this->createCount($otherClinic, $this->userForClinic($otherClinic, ['inventory.manage']), 'finalized', now(), [
            [$external, 10, 20, 10, 100],
        ]);
        $shortage->delete();

        $response = $this->actingAs($user)->get(route('inventory-counts.variance-report'));

        $response
            ->assertOk()
            ->assertSee('Divergências de inventário')
            ->assertSee('Produto com sobra')
            ->assertSee('Produto com falta')
            ->assertDontSee('Produto sem diferença')
            ->assertDontSee('Produto de outra clínica');

        $stats = $response->viewData('stats');
        $this->assertSame(3, $stats['total_items']);
        $this->assertSame(1, $stats['total_counts']);
        $this->assertSame(2, $stats['affected_products']);
        $this->assertSame(2, $stats['divergent_items']);
        $this->assertSame(33.33, $stats['accuracy_percent']);
        $this->assertSame(20.0, $stats['surplus_value']);
        $this->assertSame(15.0, $stats['shortage_value']);
        $this->assertSame(35.0, $stats['absolute_adjustment_value']);
        $this->assertSame(5.0, $stats['net_adjustment_value']);
    }

    public function test_report_aggregates_repeated_product_counts_and_applies_filters(): void
    {
        $clinic = $this->clinic('Clínica Ranking', '72000000000101');
        $user = $this->userForClinic($clinic, ['inventory.manage']);
        $target = $this->product($clinic, 'Vacina alvo', 10, 'Farmácia', 'VAC-ALVO');
        $other = $this->product($clinic, 'Shampoo fora do filtro', 4, 'Higiene', 'SHA-FORA');

        $this->createCount($clinic, $user, 'finalized', now()->subDays(20), [
            [$target, 5, 7, 2, 10],
            [$other, 5, 4, -1, 4],
        ]);
        $this->createCount($clinic, $user, 'finalized', now()->subDays(5), [
            [$target, 7, 6, -1, 10],
        ]);

        $response = $this->actingAs($user)->get(route('inventory-counts.variance-report', [
            'period' => '30',
            'direction' => 'all',
            'category' => 'Farmácia',
            'q' => 'VAC-ALVO',
        ]));

        $response
            ->assertOk()
            ->assertSee('Vacina alvo')
            ->assertDontSee('Shampoo fora do filtro');

        /** @var LengthAwarePaginator $rankings */
        $rankings = $response->viewData('rankings');
        $this->assertSame(1, $rankings->total());
        $row = $rankings->items()[0];
        $this->assertSame(2, (int) $row->count_events);
        $this->assertSame(2, (int) $row->divergence_events);
        $this->assertSame(1.0, (float) $row->net_quantity);
        $this->assertSame(3.0, (float) $row->absolute_quantity);
        $this->assertSame(20.0, (float) $row->surplus_value);
        $this->assertSame(10.0, (float) $row->shortage_value);
        $this->assertSame(10.0, (float) $row->net_value);
    }

    public function test_csv_export_uses_the_same_filters_and_does_not_leak_another_clinic(): void
    {
        $clinic = $this->clinic('Clínica CSV A', '73000000000101');
        $otherClinic = $this->clinic('Clínica CSV B', '73000000000102');
        $user = $this->userForClinic($clinic, ['inventory.manage']);
        $product = $this->product($clinic, 'Produto exportado', 8, 'Farmácia', '=EXP-001');
        $external = $this->product($otherClinic, 'Segredo externo', 50, 'Farmácia', 'EXT-999');
        $this->createCount($clinic, $user, 'finalized', now(), [[$product, 5, 7, 2, 8]]);
        $this->createCount($otherClinic, $this->userForClinic($otherClinic, ['inventory.manage']), 'finalized', now(), [
            [$external, 5, 8, 3, 50],
        ]);

        $response = $this->actingAs($user)->get(route('inventory-counts.variance-report.export', [
            'period' => '90',
            'direction' => 'surplus',
            'q' => 'EXP-001',
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Produto exportado', $content);
        $this->assertStringContainsString("'=EXP-001", $content);
        $this->assertStringContainsString('16,00', $content);
        $this->assertStringNotContainsString('Segredo externo', $content);
        $this->assertStringNotContainsString('inventory_count_id', $content);
    }

    public function test_report_and_export_require_inventory_permission(): void
    {
        $clinic = $this->clinic('Clínica Sem Relatório', '74000000000101');
        $user = $this->userForClinic($clinic, []);

        $this->actingAs($user)
            ->get(route('inventory-counts.variance-report'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('inventory-counts.variance-report.export'))
            ->assertForbidden();
    }

    private function createCount(
        Clinic $clinic,
        User $user,
        string $status,
        $finalizedAt,
        array $items,
    ): InventoryCount {
        $ulid = (string) Str::ulid();
        $count = InventoryCount::query()->create([
            'ulid' => $ulid,
            'clinic_id' => $clinic->id,
            'created_by_user_id' => $user->id,
            'finalized_by_user_id' => $status === 'finalized' ? $user->id : null,
            'code' => 'TST-'.Str::upper(substr($ulid, -8)),
            'title' => 'Contagem de teste',
            'status' => $status,
            'opened_at' => $finalizedAt?->copy()->subHour() ?? now(),
            'finalized_at' => $finalizedAt,
        ]);

        foreach ($items as [$product, $expected, $counted, $variance, $cost]) {
            $count->items()->create([
                'product_id' => $product->id,
                'expected_quantity' => $expected,
                'counted_quantity' => $counted,
                'variance_quantity' => $variance,
                'unit_cost_snapshot' => $cost,
            ]);
        }

        return $count;
    }

    private function product(
        Clinic $clinic,
        string $name,
        float $cost,
        string $category,
        ?string $sku = null,
    ): Product {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'category' => $category,
            'sku' => $sku ?? Str::upper(Str::random(8)),
            'cost_price' => $cost,
            'sale_price' => $cost * 2,
            'stock_quantity' => 10,
            'minimum_stock' => 1,
            'unit' => 'un',
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
            'name' => 'Variance report role '.Str::random(6),
            'slug' => 'variance-report-role-'.Str::lower(Str::random(8)),
            'description' => 'Role para testes de divergências de inventário',
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
