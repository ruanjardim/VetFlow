<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Saas\Models\Feature;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\PlanFeature;
use App\Modules\Saas\Models\SubscriptionOverride;
use App\Modules\Saas\Services\SubscriptionFeatureService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\SaasPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, SaasPlanSeeder::class]);
    }

    public function test_feature_resolution_uses_the_subscribed_plan(): void
    {
        $clinic = $this->clinic();
        $plan = $this->plan(['dashboard']);
        $this->subscribe($clinic, $plan);
        $features = app(SubscriptionFeatureService::class);

        $this->assertTrue($features->enabled($clinic->id, 'dashboard'));
        $this->assertFalse($features->enabled($clinic->id, 'pdv'));
    }

    public function test_subscription_override_takes_precedence_over_the_plan(): void
    {
        $clinic = $this->clinic();
        $plan = $this->plan([]);
        $subscription = $this->subscribe($clinic, $plan);
        $pdv = Feature::query()->where('key', 'pdv')->firstOrFail();
        SubscriptionOverride::query()->create(['subscription_id' => $subscription->id, 'feature_id' => $pdv->id, 'value' => true]);

        $this->assertTrue(app(SubscriptionFeatureService::class)->enabled($clinic->id, 'pdv'));
    }

    public function test_only_active_users_consume_the_plan_limit(): void
    {
        $clinic = $this->clinic();
        $plan = $this->plan([], 1);
        $this->subscribe($clinic, $plan);
        $actor = $this->userWithPermissions($clinic, ['users.manage']);
        $role = Role::query()->where('slug', 'atendimento')->firstOrFail();

        $payload = [
            'name' => 'Segundo usuário', 'email' => 'second@example.test', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'active' => '1', 'role_ids' => [$role->id],
        ];
        $this->actingAs($actor)->post(route('access-users.store'), $payload)
            ->assertSessionHasErrors('active');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.test']);

        $payload['active'] = '0';
        $this->post(route('access-users.store'), $payload)->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('users', ['email' => 'second@example.test', 'active' => false]);
    }

    public function test_feature_resolution_is_isolated_by_tenant(): void
    {
        $clinicA = $this->clinic();
        $clinicB = $this->clinic();
        $this->subscribe($clinicA, $this->plan(['pdv']));
        $this->subscribe($clinicB, $this->plan([]));
        $features = app(SubscriptionFeatureService::class);

        $this->assertTrue($features->enabled($clinicA->id, 'pdv'));
        $this->assertFalse($features->enabled($clinicB->id, 'pdv'));
    }

    public function test_tenant_administrator_cannot_access_global_saas_administration(): void
    {
        $clinic = $this->clinic();
        $user = $this->userWithPermissions($clinic, ['saas.manage']);

        $this->actingAs($user)->get(route('saas.dashboard'))->assertForbidden();
    }

    public function test_global_saas_operator_can_update_plan_and_subscription(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        $clinic = $this->clinic();
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();

        $this->actingAs($operator)->get(route('saas.dashboard'))->assertOk()->assertSee('Gestão SaaS');
        $this->get(route('saas.plans.index'))->assertOk()->assertSee('Essencial');
        $this->get(route('saas.plans.edit', $plan))->assertOk();
        $this->get(route('saas.establishments.index'))->assertOk();
        $this->get(route('saas.establishments.show', $clinic))->assertOk();
        $this->get(route('saas.onboarding.create'))->assertOk()->assertSee('Implantar novo cliente');

        $this->put(route('saas.plans.update', $plan), [
            'name' => 'Essencial Plus', 'slug' => $plan->slug, 'description' => 'Atualizado',
            'active' => '1', 'monthly_price' => 119, 'annual_price' => 1190,
            'max_users' => 3, 'max_units' => 1, 'display_order' => 10,
            'features' => ['dashboard' => '1', 'pdv' => '1'],
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('saas_plans', ['id' => $plan->id, 'name' => 'Essencial Plus', 'max_users' => 3]);

        $this->put(route('saas.subscriptions.update', $clinic), [
            'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => today()->toDateString(),
            'overrides' => ['max_users' => 4],
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(4, app(SubscriptionFeatureService::class)->limit($clinic->id, 'max_users'));
    }

    public function test_direct_module_url_is_blocked_when_plan_does_not_include_feature(): void
    {
        $clinic = $this->clinic();
        $this->subscribe($clinic, $this->plan([]));
        $user = $this->userWithPermissions($clinic, ['sales.manage']);

        $this->actingAs($user)->get(route('sales.index'))->assertForbidden();
    }

    public function test_access_requires_both_user_permission_and_plan_feature(): void
    {
        $clinic = $this->clinic();
        $this->subscribe($clinic, $this->plan(['pdv']));
        $withoutPermission = $this->userWithPermissions($clinic, ['dashboard.view']);
        $withPermission = $this->userWithPermissions($clinic, ['sales.manage']);

        $this->actingAs($withoutPermission)->get(route('sales.index'))->assertForbidden();
        $this->actingAs($withPermission)->get(route('sales.index'))->assertOk();
    }

    public function test_onboarding_creates_tenant_subscription_and_initial_administrator(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        $plan = Plan::query()->where('slug', 'profissional')->firstOrFail();

        $this->actingAs($operator)->post(route('saas.onboarding.store'), $this->onboardingPayload($plan))
            ->assertSessionDoesntHaveErrors();

        $clinic = Clinic::query()->where('cnpj', '12345678000199')->firstOrFail();
        $admin = User::query()->where('email', 'admin@novo.test')->firstOrFail();
        $this->assertSame($plan->id, $clinic->subscription->plan_id);
        $this->assertSame($clinic->id, $admin->clinic_id);
        $this->assertTrue($admin->hasRole('administrador'));
    }

    public function test_onboarding_rolls_back_everything_when_administrator_role_is_missing(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        Role::query()->where('slug', 'administrador')->delete();
        $plan = Plan::query()->where('slug', 'profissional')->firstOrFail();

        $this->actingAs($operator)->post(route('saas.onboarding.store'), $this->onboardingPayload($plan))
            ->assertSessionHasErrors('admin.name');

        $this->assertDatabaseMissing('clinics', ['cnpj' => '12345678000199']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@novo.test']);
    }

    public function test_new_and_existing_style_clinics_receive_legacy_compatibility_access(): void
    {
        $clinic = $this->clinic();

        $this->assertSame('legacy-internal', $clinic->subscription->plan->slug);
        $this->assertTrue($clinic->hasFeature('dashboard'));
        $this->assertTrue($clinic->hasFeature('pdv'));
        $this->assertNull($clinic->featureLimit('max_users'));
    }

    private function clinic(): Clinic
    {
        $number = str_pad((string) random_int(1, 99999999999999), 14, '0', STR_PAD_LEFT);

        return Clinic::query()->create([
            'corporate_name' => 'Clínica '.$number, 'trade_name' => 'Vet '.$number,
            'cnpj' => $number, 'business_type' => 'veterinary_clinic', 'active' => true,
        ]);
    }

    /** @param array<int, string> $enabled */
    private function plan(array $enabled, ?int $maxUsers = null): Plan
    {
        $plan = Plan::query()->create([
            'name' => 'Plano '.Str::random(8), 'slug' => 'plan-'.Str::lower(Str::random(10)),
            'active' => true, 'max_users' => $maxUsers, 'max_units' => 1, 'internal' => false,
        ]);
        Feature::query()->where('type', 'boolean')->get()->each(fn (Feature $feature) => PlanFeature::query()->create([
            'plan_id' => $plan->id, 'feature_id' => $feature->id, 'value' => in_array($feature->key, $enabled, true),
        ]));

        return $plan;
    }

    private function subscribe(Clinic $clinic, Plan $plan)
    {
        $subscription = $clinic->subscription;
        $subscription->update(['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => today()]);

        return $subscription->fresh();
    }

    /** @param array<int, string> $permissions */
    private function userWithPermissions(?Clinic $clinic, array $permissions): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic?->id, 'active' => true]);
        $role = Role::query()->create([
            'clinic_id' => null, 'name' => 'Teste '.Str::random(5), 'slug' => 'test-'.Str::lower(Str::random(10)),
            'system' => false, 'active' => true,
        ]);
        foreach ($permissions as $slug) {
            $permission = Permission::query()->where('slug', $slug)->firstOrFail();
            $role->permissions()->attach($permission->id);
        }
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function onboardingPayload(Plan $plan): array
    {
        return [
            'clinic' => [
                'corporate_name' => 'Novo Vet Ltda', 'trade_name' => 'Novo Vet',
                'business_type' => 'mixed', 'cnpj' => '12345678000199',
                'email' => 'contato@novo.test', 'phone' => '11999999999',
            ],
            'plan_id' => $plan->id,
            'overrides' => ['max_users' => 8],
            'admin' => [
                'name' => 'Admin Inicial', 'email' => 'admin@novo.test', 'phone' => '11988888888',
                'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            ],
        ];
    }
}
