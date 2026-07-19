<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
