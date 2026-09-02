<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryCountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_count_snapshots_only_active_products_in_the_selected_scope_and_clinic(): void
    {
        $clinic = $this->clinic('Clínica Contagem A', '61000000000101');
        $otherClinic = $this->clinic('Clínica Contagem B', '61000000000102');
        $user = $this->userForClinic($clinic, ['inventory.manage']);

        $included = $this->product($clinic, 'Vacina ativa', 8.5, 12.25, true, 'Farmácia');
        $this->product($clinic, 'Ração ativa', 4, 20, true, 'Alimentos');
        $this->product($clinic, 'Vacina inativa', 3, 9, false, 'Farmácia');
        $this->product($otherClinic, 'Vacina externa', 99, 9, true, 'Farmácia');

        $response = $this->actingAs($user)->post(route('inventory-counts.store'), [
            'title' => 'Conferência da farmácia',
            'category' => 'Farmácia',
            'notes' => 'Prateleira refrigerada',
        ]);

        $count = InventoryCount::query()->firstOrFail();

        $response->assertRedirect(route('inventory-counts.show', $count->id));
        $this->assertSame('draft', $count->status);
        $this->assertSame($clinic->id, $count->clinic_id);
        $this->assertSame($user->id, $count->created_by_user_id);
        $this->assertSame('Farmácia', $count->category);
        $this->assertCount(1, $count->items);
        $this->assertSame($included->id, $count->items->first()->product_id);
        $this->assertSame(8.5, (float) $count->items->first()->expected_quantity);
        $this->assertSame(12.25, (float) $count->items->first()->unit_cost_snapshot);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_finalizing_applies_only_divergences_and_cannot_be_applied_twice(): void
    {
        $clinic = $this->clinic('Clínica Ajustes', '62000000000101');
        $user = $this->userForClinic($clinic, ['inventory.manage']);
        $surplus = $this->product($clinic, 'Produto com sobra', 10, 4);
        $shortage = $this->product($clinic, 'Produto com falta', 5, 7);
        $matching = $this->product($clinic, 'Produto conferido', 7, 2);
        $count = $this->openCount($user, 'Contagem completa');
        $items = $count->items->keyBy('product_id');

        $this->actingAs($user)
            ->put(route('inventory-counts.update', $count->id), [
                'counts' => [
                    $items[$surplus->id]->id => 12,
                    $items[$shortage->id]->id => 3,
                    $items[$matching->id]->id => 7,
                ],
                'notes' => 'Dupla conferência concluída',
            ])
            ->assertRedirect(route('inventory-counts.show', $count->id));

        $this->actingAs($user)
            ->post(route('inventory-counts.finalize', $count->id))
            ->assertRedirect(route('inventory-counts.show', $count->id));

        $count->refresh();
        $this->assertSame('finalized', $count->status);
        $this->assertSame($user->id, $count->finalized_by_user_id);
        $this->assertNotNull($count->finalized_at);
        $this->assertSame(12.0, (float) $surplus->fresh()->stock_quantity);
        $this->assertSame(3.0, (float) $shortage->fresh()->stock_quantity);
        $this->assertSame(7.0, (float) $matching->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $surplus->id,
            'type' => 'entry',
            'source' => 'inventory_count',
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $shortage->id,
            'type' => 'exit',
            'source' => 'inventory_count',
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $matching->id,
            'source' => 'inventory_count',
        ]);

        $movement = DB::table('inventory_movements')->where('product_id', $surplus->id)->first();
        $metadata = json_decode($movement->metadata, true);
        $this->assertSame($count->id, $metadata['inventory_count_id']);
        $this->assertSame(10.0, (float) $metadata['expected_quantity']);
        $this->assertSame(12.0, (float) $metadata['counted_quantity']);

        $this->from(route('inventory-counts.show', $count->id))
            ->actingAs($user)
            ->post(route('inventory-counts.finalize', $count->id))
            ->assertRedirect(route('inventory-counts.show', $count->id))
            ->assertSessionHasErrors('inventory_count');

        $this->assertDatabaseCount('inventory_movements', 2);

        $this->actingAs($user)
            ->get(route('inventory-movements.edit', $movement->id))
            ->assertRedirect(route('inventory-movements.index'));
    }

    public function test_finalization_is_blocked_when_book_stock_changed_after_opening(): void
    {
        $clinic = $this->clinic('Clínica Saldo Concorrente', '63000000000101');
        $user = $this->userForClinic($clinic, ['inventory.manage']);
        $product = $this->product($clinic, 'Produto movimentado', 10, 6);
        $count = $this->openCount($user, 'Contagem protegida');
        $item = $count->items->firstWhere('product_id', $product->id);

        $this->actingAs($user)->put(route('inventory-counts.update', $count->id), [
            'counts' => [$item->id => 9],
        ]);

        $this->actingAs($user);
        app(InventoryMovementService::class)->create([
            'clinic_id' => $clinic->id,
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 2,
            'source' => 'manual',
            'reason' => 'Entrada durante a conferência',
        ]);

        $this->from(route('inventory-counts.show', $count->id))
            ->post(route('inventory-counts.finalize', $count->id))
            ->assertRedirect(route('inventory-counts.show', $count->id))
            ->assertSessionHasErrors('inventory_count');

        $this->assertSame('draft', $count->fresh()->status);
        $this->assertSame(12.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseMissing('inventory_movements', ['source' => 'inventory_count']);
    }

    public function test_cancelling_keeps_stock_untouched_and_makes_the_count_immutable(): void
    {
        $clinic = $this->clinic('Clínica Cancelamento', '64000000000101');
        $user = $this->userForClinic($clinic, ['inventory.manage']);
        $product = $this->product($clinic, 'Produto sem ajuste', 6, 5);
        $count = $this->openCount($user, 'Contagem cancelada');
        $item = $count->items->first();

        $this->actingAs($user)
            ->post(route('inventory-counts.cancel', $count->id), [
                'cancellation_reason' => 'Área ainda recebendo mercadorias',
            ])
            ->assertRedirect(route('inventory-counts.show', $count->id));

        $count->refresh();
        $this->assertSame('cancelled', $count->status);
        $this->assertSame($user->id, $count->cancelled_by_user_id);
        $this->assertSame('Área ainda recebendo mercadorias', $count->cancellation_reason);
        $this->assertSame(6.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->from(route('inventory-counts.show', $count->id))
            ->actingAs($user)
            ->put(route('inventory-counts.update', $count->id), [
                'counts' => [$item->id => 1],
            ])
            ->assertSessionHasErrors('inventory_count');

        $this->assertNull($item->fresh()->counted_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_counts_require_permission_and_are_isolated_by_clinic(): void
    {
        $clinic = $this->clinic('Clínica Isolada A', '65000000000101');
        $otherClinic = $this->clinic('Clínica Isolada B', '65000000000102');
        $allowedUser = $this->userForClinic($clinic, ['inventory.manage']);
        $otherUser = $this->userForClinic($otherClinic, ['inventory.manage']);
        $forbiddenUser = $this->userForClinic($clinic, []);
        $this->product($clinic, 'Produto isolado', 2, 3);
        $count = $this->openCount($allowedUser, 'Contagem isolada');

        $this->actingAs($otherUser)
            ->get(route('inventory-counts.show', $count->id))
            ->assertNotFound();

        $this->actingAs($forbiddenUser)
            ->get(route('inventory-counts.index'))
            ->assertForbidden();
    }

    public function test_global_operator_must_choose_the_active_clinic_for_the_snapshot(): void
    {
        $clinic = $this->clinic('Clínica Global A', '66000000000101');
        $selectedClinic = $this->clinic('Clínica Global B', '66000000000102');
        $inactiveClinic = $this->clinic('Clínica Global Inativa', '66000000000103');
        $inactiveClinic->update(['active' => false]);
        $user = $this->userForClinic(null, ['inventory.manage']);
        $this->product($clinic, 'Produto de outra clínica', 20, 2);
        $selectedProduct = $this->product($selectedClinic, 'Produto da clínica escolhida', 4, 3);

        $this->actingAs($user)
            ->post(route('inventory-counts.store'), [
                'clinic_id' => $selectedClinic->id,
                'title' => 'Contagem global direcionada',
            ])
            ->assertSessionHasNoErrors();

        $count = InventoryCount::query()->firstOrFail();
        $this->assertSame($selectedClinic->id, $count->clinic_id);
        $this->assertSame([$selectedProduct->id], $count->items->pluck('product_id')->all());

        $this->from(route('inventory-counts.create'))
            ->post(route('inventory-counts.store'), [
                'clinic_id' => $inactiveClinic->id,
                'title' => 'Contagem inválida',
            ])
            ->assertRedirect(route('inventory-counts.create'))
            ->assertSessionHasErrors('clinic_id');

        $this->assertDatabaseCount('inventory_counts', 1);
    }

    private function openCount(User $user, string $title): InventoryCount
    {
        $this->actingAs($user)->post(route('inventory-counts.store'), [
            'title' => $title,
        ])->assertSessionHasNoErrors();

        return InventoryCount::query()->latest('id')->with('items')->firstOrFail();
    }

    private function product(
        Clinic $clinic,
        string $name,
        float $stock,
        float $cost,
        bool $active = true,
        ?string $category = null,
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
            'unit' => 'un',
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

    private function userForClinic(?Clinic $clinic, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Inventory count role '.Str::random(6),
            'slug' => 'inventory-count-role-'.Str::lower(Str::random(8)),
            'description' => 'Role para testes de inventário rotativo',
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
