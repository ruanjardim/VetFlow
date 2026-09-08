<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationImport;
use App\Modules\Implementation\Services\ImplementationDataQualityService;
use App\Modules\Implementation\Services\ImplementationPilotChecklistService;
use App\Modules\Implementation\Services\ImplementationPilotReadinessService;
use App\Modules\Implementation\Services\ImplementationPilotReleaseService;
use App\Modules\Implementation\Services\ImplementationReadinessService;
use App\Modules\Products\Models\Product;
use App\Support\Demo\WalkthroughDemoFixture;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\WalkthroughDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkthroughDemoResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_walkthrough_seeder_does_not_assign_roles_to_unrelated_users_when_authorization_exists(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $unrelated = User::factory()->create([
            'email' => 'unrelated@example.test',
            'active' => true,
        ]);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $unrelated->id,
            'deleted_at' => null,
        ]);

        $this->seed(WalkthroughDemoSeeder::class);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $unrelated->id,
            'deleted_at' => null,
        ]);
    }

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
        $unrelatedImport = ImplementationImport::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $unrelatedUser->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $unrelatedUser->name,
            'entity_type' => 'tutors',
            'entity_label' => 'Responsáveis',
            'data_source' => 'csv',
            'file_name' => 'preservado.csv',
            'total_rows' => 1,
            'imported_count' => 1,
            'invalid_rows' => 0,
            'completed_at' => now()->subDays(2),
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
        $this->assertDatabaseMissing('suppliers', [
            'document' => WalkthroughDemoFixture::SUPPLIER_DOCUMENT,
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
        $this->assertDatabaseHas('implementation_imports', [
            'id' => $unrelatedImport->id,
            'file_name' => 'preservado.csv',
        ]);
        $this->assertSame(
            0,
            ImplementationImport::query()
                ->whereIn('file_name', array_values(WalkthroughDemoFixture::IMPLEMENTATION_IMPORT_FILES))
                ->count()
        );
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
        $this->assertSame(
            6,
            ImplementationImport::query()
                ->whereIn('file_name', array_values(WalkthroughDemoFixture::IMPLEMENTATION_IMPORT_FILES))
                ->count()
        );
        $this->assertDatabaseCount('implementation_pilot_checks', 3);
        $this->assertDatabaseCount('implementation_pilot_releases', 1);
    }

    public function test_walkthrough_seeds_a_truthful_blocked_pilot_scenario(): void
    {
        $this->seed(WalkthroughDemoSeeder::class);

        $clinic = Clinic::query()
            ->where('cnpj', WalkthroughDemoFixture::CLINIC_CNPJ)
            ->firstOrFail();
        $clinics = collect([$clinic]);
        $coverage = app(ImplementationReadinessService::class)->forClinics($clinics);
        $quality = app(ImplementationDataQualityService::class)->forClinics($clinics, $coverage);
        $checklist = app(ImplementationPilotChecklistService::class)->forClinics($clinics);
        $release = app(ImplementationPilotReleaseService::class)->forClinics($clinics);
        $readiness = app(ImplementationPilotReadinessService::class)->forClinics(
            $clinics,
            $coverage,
            $quality,
            $checklist,
            $release
        );

        $this->assertSame(6, $coverage[0]['completed_blocks']);
        $this->assertSame(6, $quality[0]['evaluated_blocks']);
        $this->assertSame(2, $quality[0]['total_issues']);
        $this->assertSame(3, $checklist[0]['completed_checks']);
        $this->assertSame(1, $release[0]['revision']);
        $this->assertFalse($readiness[0]['gates_passed']);
        $this->assertSame('blocked', $readiness[0]['status']['key']);
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
