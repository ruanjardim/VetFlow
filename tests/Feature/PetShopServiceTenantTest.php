<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\PetShopServices\Models\PetShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PetShopServiceTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_operator_selects_the_clinic_for_a_petshop_service(): void
    {
        $clinic = $this->clinic('PetShop Horizonte', '00000000001101');
        $user = $this->operator(null);

        $this->actingAs($user)
            ->get(route('petshop-services.create'))
            ->assertOk()
            ->assertSee('name="clinic_id"', false)
            ->assertSee('PetShop Horizonte');

        $this->actingAs($user)
            ->post(route('petshop-services.store'), $this->serviceData())
            ->assertSessionHasErrors('clinic_id');

        $this->actingAs($user)
            ->post(route('petshop-services.store'), array_merge($this->serviceData(), [
                'clinic_id' => $clinic->id,
            ]))
            ->assertRedirect(route('petshop-services.index'))
            ->assertSessionDoesntHaveErrors();

        $service = PetShopService::query()->firstOrFail();

        $this->assertSame($clinic->id, $service->clinic_id);

        $this->actingAs($user)
            ->get(route('petshop-services.index'))
            ->assertOk()
            ->assertSee('PetShop Horizonte')
            ->assertSee('Banho e Tosa');
    }

    public function test_clinic_operator_cannot_override_the_service_clinic(): void
    {
        $clinic = $this->clinic('PetShop Local', '00000000001102');
        $otherClinic = $this->clinic('PetShop Externo', '00000000001103');
        $user = $this->operator($clinic);

        $this->actingAs($user)
            ->post(route('petshop-services.store'), array_merge($this->serviceData(), [
                'clinic_id' => $otherClinic->id,
            ]))
            ->assertRedirect(route('petshop-services.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('petshop_services', [
            'clinic_id' => $clinic->id,
            'name' => 'Banho e Tosa',
        ]);
        $this->assertDatabaseMissing('petshop_services', [
            'clinic_id' => $otherClinic->id,
            'name' => 'Banho e Tosa',
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

    private function operator(?Clinic $clinic): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $role = Role::query()->create([
            'name' => 'Gestor PetShop '.Str::random(6),
            'slug' => 'gestor-petshop-'.Str::lower(Str::random(8)),
            'system' => false,
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'petshop-services.manage'],
            [
                'name' => 'Gerenciar servicos PetShop',
                'group' => 'Tests',
                'active' => true,
            ]
        );
        $role->permissions()->attach($permission->id);
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function serviceData(): array
    {
        return [
            'name' => 'Banho e Tosa',
            'category' => 'Banho e Tosa',
            'base_price' => 80,
            'small_price' => 60,
            'medium_price' => 80,
            'large_price' => 110,
            'giant_price' => 140,
            'duration_minutes' => 90,
            'requires_appointment' => true,
            'active' => true,
        ];
    }
}
