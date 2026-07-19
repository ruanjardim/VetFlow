<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sale_applies_stock_and_financial_records_for_current_clinic(): void
    {
        $clinic = $this->clinic('Clinica Operacional A', '00000000000201');
        $product = $this->product($clinic, 'Vacina V10', stock: 10, costPrice: 8, salePrice: 25);
        $user = $this->userForClinic($clinic, ['sales.manage']);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'status' => 'completed',
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'description' => 'Vacina V10',
                    'quantity' => '2',
                    'unit_price' => '25',
                ],
            ],
            'payments' => [
                [
                    'method' => 'pix',
                    'amount' => '50',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('sales.index'))
            ->assertSessionDoesntHaveErrors();

        $sale = Sale::query()->with('financialTransaction')->firstOrFail();
        $movement = InventoryMovement::query()->where('sale_id', $sale->id)->firstOrFail();
        $financialTransaction = $sale->financialTransaction;

        $this->assertSame($clinic->id, (int) $sale->clinic_id);
        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertTrue($sale->stock_applied);
        $this->assertTrue($sale->financial_applied);
        $this->assertEquals(50.0, (float) $sale->total);
        $this->assertEquals(8.0, (float) $product->fresh()->stock_quantity);

        $this->assertSame($clinic->id, (int) $movement->clinic_id);
        $this->assertSame('exit', $movement->type);
        $this->assertSame('sale', $movement->source);
        $this->assertEquals(10.0, (float) $movement->balance_before);
        $this->assertEquals(8.0, (float) $movement->balance_after);

        $this->assertNotNull($financialTransaction);
        $this->assertSame($clinic->id, (int) $financialTransaction->clinic_id);
        $this->assertSame('income', $financialTransaction->type);
        $this->assertSame('paid', $financialTransaction->status);
        $this->assertEquals(50.0, (float) $financialTransaction->amount);
    }

    public function test_sale_rejects_product_from_another_clinic_before_side_effects(): void
    {
        $clinicA = $this->clinic('Clinica Operacional B', '00000000000211');
        $clinicB = $this->clinic('Clinica Operacional C', '00000000000212');
        $productFromOtherClinic = $this->product($clinicB, 'Produto de outra clinica', stock: 6, salePrice: 30);
        $user = $this->userForClinic($clinicA, ['sales.manage']);

        $response = $this
            ->actingAs($user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'status' => 'completed',
                'items' => [
                    [
                        'type' => 'product',
                        'product_id' => $productFromOtherClinic->id,
                        'description' => 'Tentativa produto externo',
                        'quantity' => '1',
                        'unit_price' => '30',
                    ],
                ],
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => '30',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('sales.create'))
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_inventory_movement_updates_stock_and_rejects_cross_clinic_product(): void
    {
        $clinicA = $this->clinic('Clinica Operacional D', '00000000000221');
        $clinicB = $this->clinic('Clinica Operacional E', '00000000000222');
        $ownProduct = $this->product($clinicA, 'Soro fisiologico', stock: 5);
        $otherProduct = $this->product($clinicB, 'Produto externo estoque', stock: 4);
        $user = $this->userForClinic($clinicA, ['inventory.manage']);

        $response = $this->actingAs($user)->post(route('inventory-movements.store'), [
            'product_id' => $ownProduct->id,
            'type' => 'entry',
            'quantity' => '3',
            'unit_cost' => '12.50',
            'reason' => 'Entrada manual',
        ]);

        $response
            ->assertRedirect(route('inventory-movements.index'))
            ->assertSessionDoesntHaveErrors();

        $movement = InventoryMovement::query()->where('product_id', $ownProduct->id)->firstOrFail();

        $this->assertSame($clinicA->id, (int) $movement->clinic_id);
        $this->assertSame('entry', $movement->type);
        $this->assertEquals(5.0, (float) $movement->balance_before);
        $this->assertEquals(8.0, (float) $movement->balance_after);
        $this->assertEquals(8.0, (float) $ownProduct->fresh()->stock_quantity);

        $response = $this
            ->from(route('inventory-movements.create'))
            ->post(route('inventory-movements.store'), [
                'product_id' => $otherProduct->id,
                'type' => 'exit',
                'quantity' => '1',
                'reason' => 'Tentativa produto externo',
            ]);

        $response
            ->assertRedirect(route('inventory-movements.create'))
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseMissing('inventory_movements', [
            'clinic_id' => $clinicA->id,
            'product_id' => $otherProduct->id,
        ]);
        $this->assertEquals(
            4.0,
            (float) DB::table('products')->where('id', $otherProduct->id)->value('stock_quantity')
        );
    }

    public function test_financial_transactions_are_created_and_updated_only_inside_current_clinic(): void
    {
        $clinicA = $this->clinic('Clinica Operacional F', '00000000000231');
        $clinicB = $this->clinic('Clinica Operacional G', '00000000000232');
        $ownSupplier = $this->supplier($clinicA, 'Fornecedor local');
        $otherSupplier = $this->supplier($clinicB, 'Fornecedor externo');
        $ownTransaction = $this->financialTransaction($clinicA, 'Recebimento local');
        $otherTransaction = $this->financialTransaction($clinicB, 'Recebimento externo');
        $user = $this->userForClinic($clinicA, ['financial.manage']);

        $response = $this->actingAs($user)->post(route('financial-transactions.store'), [
            'supplier_id' => $ownSupplier->id,
            'type' => 'expense',
            'description' => 'Compra local',
            'amount' => '125.90',
            'due_date' => today()->toDateString(),
            'status' => 'pending',
        ]);

        $response
            ->assertRedirect(route('financial-transactions.index'))
            ->assertSessionDoesntHaveErrors();

        $created = FinancialTransaction::query()
            ->where('description', 'Compra local')
            ->firstOrFail();

        $this->assertSame($clinicA->id, (int) $created->clinic_id);
        $this->assertSame($ownSupplier->id, (int) $created->supplier_id);

        $response = $this
            ->from(route('financial-transactions.create'))
            ->post(route('financial-transactions.store'), [
                'supplier_id' => $otherSupplier->id,
                'type' => 'expense',
                'description' => 'Compra externa indevida',
                'amount' => '99.90',
                'status' => 'pending',
            ]);

        $response
            ->assertRedirect(route('financial-transactions.create'))
            ->assertSessionHasErrors('supplier_id');

        $this->patch(route('financial-transactions.pay', $ownTransaction->id))
            ->assertRedirect(route('financial-transactions.index'));

        $this->assertSame('paid', $ownTransaction->fresh()->status);

        $this->patch(route('financial-transactions.pay', $otherTransaction->id))
            ->assertNotFound();

        $this->assertSame(
            'pending',
            DB::table('financial_transactions')->where('id', $otherTransaction->id)->value('status')
        );
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

        $this->grantPermissions($user, $permissionSlugs);

        return $user;
    }

    private function product(
        Clinic $clinic,
        string $name,
        float $stock = 0,
        float $costPrice = 0,
        float $salePrice = 0
    ): Product {
        return Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'stock_quantity' => $stock,
            'active' => true,
        ]);
    }

    private function supplier(Clinic $clinic, string $name): Supplier
    {
        return Supplier::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function financialTransaction(Clinic $clinic, string $description): FinancialTransaction
    {
        return FinancialTransaction::query()->create([
            'clinic_id' => $clinic->id,
            'type' => 'income',
            'description' => $description,
            'amount' => 80,
            'due_date' => today()->toDateString(),
            'status' => 'pending',
        ]);
    }

    private function grantPermissions(User $user, array $permissionSlugs): void
    {
        $role = Role::query()->create([
            'name' => 'Test role '.Str::random(6),
            'slug' => 'test-role-'.Str::lower(Str::random(8)),
            'description' => 'Test role',
            'system' => false,
            'active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $permissionSlug)),
                    'description' => 'Test permission',
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
    }
}
