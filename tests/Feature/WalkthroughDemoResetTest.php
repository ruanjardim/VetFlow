<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Support\Demo\WalkthroughDemoFixture;
use Database\Seeders\WalkthroughDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkthroughDemoResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_removes_only_identified_walkthrough_data(): void
    {
        $this->seed(WalkthroughDemoSeeder::class);

        $clinic = Clinic::query()
            ->where('cnpj', WalkthroughDemoFixture::CLINIC_CNPJ)
            ->firstOrFail();
        $unrelatedUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'email' => 'preservado@vetflow.test',
        ]);
        $unrelatedProduct = Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto preservado',
            'sku' => 'PRESERVADO-001',
            'cost_price' => 10,
            'sale_price' => 20,
            'stock_quantity' => 2,
            'minimum_stock' => 1,
            'unit' => 'un',
            'active' => true,
        ]);

        $this->artisan('vetflow:demo:reset', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'cnpj' => WalkthroughDemoFixture::CLINIC_CNPJ,
        ]);
        $this->assertSoftDeleted('users', [
            'email' => WalkthroughDemoFixture::USER_EMAIL,
        ]);
        $this->assertDatabaseMissing('tutors', [
            'email' => WalkthroughDemoFixture::TUTOR_EMAIL,
        ]);
        $this->assertDatabaseMissing('products', [
            'sku' => WalkthroughDemoFixture::PRODUCT_SKUS[0],
        ]);
        $this->assertDatabaseMissing('sales', [
            'code' => WalkthroughDemoFixture::SALE_CODE,
        ]);
        $this->assertDatabaseMissing('global_products', [
            'gtin' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[0],
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $unrelatedUser->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $unrelatedProduct->id,
            'deleted_at' => null,
        ]);
    }

    public function test_reset_can_reseed_the_walkthrough_without_duplicates(): void
    {
        $this->seed(WalkthroughDemoSeeder::class);

        $this->artisan('vetflow:demo:reset', [
            '--force' => true,
            '--reseed' => true,
        ])->assertSuccessful();

        $this->artisan('vetflow:demo:reset', [
            '--force' => true,
            '--reseed' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('clinics', 1);
        $this->assertSame(
            1,
            User::query()
                ->where('email', WalkthroughDemoFixture::USER_EMAIL)
                ->count()
        );
        $this->assertSame(
            count(WalkthroughDemoFixture::PRODUCT_SKUS),
            Product::query()
                ->whereIn('sku', WalkthroughDemoFixture::PRODUCT_SKUS)
                ->count()
        );
        $this->assertDatabaseHas('sales', [
            'code' => WalkthroughDemoFixture::SALE_CODE,
            'deleted_at' => null,
        ]);
    }

    public function test_reset_is_blocked_outside_local_and_testing_environments(): void
    {
        $this->seed(WalkthroughDemoSeeder::class);
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('vetflow:demo:reset', ['--force' => true])
            ->assertFailed();

        $this->assertDatabaseHas('sales', [
            'code' => WalkthroughDemoFixture::SALE_CODE,
        ]);
    }
}
