<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_user_only_reads_records_from_own_clinic(): void
    {
        $clinicA = $this->clinic('Clinica A', '00000000000101');
        $clinicB = $this->clinic('Clinica B', '00000000000102');

        Product::query()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Produto Clinica A',
        ]);
        $otherProduct = Product::query()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Produto Clinica B',
        ]);

        $this->actingAs($this->clinicUser($clinicA));

        $this->assertSame(['Produto Clinica A'], Product::query()->pluck('name')->all());
        $this->assertNull(Product::query()->find($otherProduct->id));
    }

    public function test_clinic_user_creates_and_updates_records_with_current_clinic(): void
    {
        $clinicA = $this->clinic('Clinica A', '00000000000111');
        $clinicB = $this->clinic('Clinica B', '00000000000112');

        $this->actingAs($this->clinicUser($clinicA));

        $product = Product::query()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Produto carimbado',
        ]);

        $this->assertSame($clinicA->id, $product->fresh()->clinic_id);

        $product->update([
            'clinic_id' => $clinicB->id,
            'name' => 'Produto ainda da Clinica A',
        ]);

        $this->assertSame($clinicA->id, $product->fresh()->clinic_id);
    }

    public function test_global_user_reads_all_clinics_and_can_choose_clinic_when_creating(): void
    {
        $clinicA = $this->clinic('Clinica A', '00000000000121');
        $clinicB = $this->clinic('Clinica B', '00000000000122');

        Product::query()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Produto A',
        ]);
        Product::query()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Produto B',
        ]);

        $this->actingAs(User::factory()->create([
            'active' => true,
            'clinic_id' => null,
        ]));

        $this->assertSame(2, Product::query()->count());

        $created = Product::query()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Produto global para B',
        ]);

        $this->assertSame($clinicB->id, $created->fresh()->clinic_id);
    }

    public function test_new_clinical_core_tables_are_tenant_scoped(): void
    {
        $clinicA = $this->clinic('Clinica A', '00000000000131');
        $clinicB = $this->clinic('Clinica B', '00000000000132');

        Tutor::query()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Tutor Clinica A',
            'phone' => '21999990001',
        ]);
        Tutor::query()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Tutor Clinica B',
            'phone' => '21999990002',
        ]);

        $this->actingAs($this->clinicUser($clinicA));

        $this->assertSame(['Tutor Clinica A'], Tutor::query()->pluck('name')->all());
    }

    public function test_other_existing_tenant_models_are_scoped_too(): void
    {
        $clinicA = $this->clinic('Clinica A', '00000000000141');
        $clinicB = $this->clinic('Clinica B', '00000000000142');

        Supplier::query()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Fornecedor A',
        ]);
        Supplier::query()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Fornecedor B',
        ]);

        $this->actingAs($this->clinicUser($clinicB));

        $this->assertSame(['Fornecedor B'], Supplier::query()->pluck('name')->all());
    }

    public function test_clinic_create_requires_core_fields(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => null,
        ]);
        $this->grantPermissions($user, ['clinics.manage']);

        $response = $this->actingAs($user)
            ->from(route('clinics.create'))
            ->post(route('clinics.store'), [
                'active' => '1',
            ]);

        $response
            ->assertRedirect(route('clinics.create'))
            ->assertSessionHasErrors(['corporate_name', 'trade_name', 'cnpj']);

        $this->assertDatabaseCount('clinics', 0);
    }

    public function test_global_user_can_create_valid_clinic(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => null,
        ]);
        $this->grantPermissions($user, ['clinics.manage']);

        $response = $this->actingAs($user)->post(route('clinics.store'), [
            'corporate_name' => 'Clinica Implantacao LTDA',
            'trade_name' => 'Clinica Implantacao',
            'cnpj' => '00000000000991',
            'email' => 'implantacao@vetflow.local',
            'phone' => '21999990000',
            'city' => 'Sao Goncalo',
            'state' => 'RJ',
            'active' => '1',
        ]);

        $response
            ->assertRedirect(route('clinics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clinics', [
            'corporate_name' => 'Clinica Implantacao LTDA',
            'trade_name' => 'Clinica Implantacao',
            'cnpj' => '00000000000991',
            'active' => true,
        ]);
    }

    public function test_clinic_user_cannot_manage_global_clinic_registry_even_with_permission(): void
    {
        $clinic = $this->clinic('Clinica Restrita', '00000000000993');
        $user = $this->clinicUser($clinic);
        $this->grantPermissions($user, ['dashboard.view', 'clinics.manage']);

        $this->actingAs($user)
            ->get(route('clinics.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('clinics.index'));
    }

    public function test_operational_forms_warn_global_user_when_no_clinic_exists(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => null,
        ]);
        $this->grantPermissions($user, ['clinics.manage', 'purchase-entries.manage', 'sales.manage']);

        $this->actingAs($user)
            ->get(route('purchase-entries.create'))
            ->assertOk()
            ->assertSee('Nenhuma clinica cadastrada.')
            ->assertSee(route('clinics.create'));

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Nenhuma clinica cadastrada.')
            ->assertSee(route('clinics.create'));
    }

    public function test_sales_create_preselects_only_clinic_for_global_users(): void
    {
        $clinic = $this->clinic('Clinica Unica PDV', '00000000000992');
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => null,
        ]);
        $this->grantPermissions($user, ['sales.manage']);

        $response = $this->actingAs($user)->get(route('sales.create'));

        $response
            ->assertOk()
            ->assertSee('value="'.$clinic->id.'" selected', false);
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

    private function clinicUser(Clinic $clinic): User
    {
        return User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic->id,
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
