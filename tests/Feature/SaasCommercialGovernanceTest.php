<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Saas\Models\LegalAcceptance;
use App\Modules\Saas\Models\LegalDocument;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Services\CouponService;
use App\Modules\Saas\Services\LegalDocumentService;
use App\Modules\Saas\Services\SubscriptionPriceService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\LegalDocumentSeeder;
use Database\Seeders\SaasPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SaasCommercialGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, SaasPlanSeeder::class, LegalDocumentSeeder::class]);
    }

    public function test_coupon_uses_only_supported_percentages_and_freezes_exact_price_and_period(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $clinic = $this->clinicWithPlan($plan);

        $this->actingAs($operator)->post(route('saas.coupons.store'), [
            'code' => 'invalido15', 'percentage' => 15, 'active' => 1,
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
            'duration_months' => 1,
        ])->assertSessionHasErrors('percentage');

        $coupon = app(CouponService::class)->save([
            'code' => 'lancamento40', 'percentage' => 40, 'active' => true,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(),
            'duration_months' => 2, 'max_redemptions' => 10, 'plan_ids' => [$plan->id],
        ], $operator);
        $promotion = app(CouponService::class)->apply($clinic->subscription, $coupon->code, $operator, today());
        $quote = app(SubscriptionPriceService::class)->quote($clinic->subscription->fresh());

        $this->assertSame('LANCAMENTO40', $promotion->coupon_code);
        $this->assertSame(9700, $promotion->base_price_cents);
        $this->assertSame(3880, $promotion->discount_cents);
        $this->assertSame(5820, $promotion->final_price_cents);
        $this->assertSame(today()->copy()->addMonthsNoOverflow(2)->subDay()->toDateString(), $promotion->ends_on->toDateString());
        $this->assertSame(5820, $quote['final_price_cents']);
        $this->assertDatabaseHas('audit_events', ['event' => 'saas.coupon.applied', 'clinic_id' => $clinic->id]);
    }

    public function test_promotions_do_not_stack_and_active_promotion_blocks_plan_change(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $otherPlan = Plan::query()->where('slug', 'profissional')->firstOrFail();
        $clinic = $this->clinicWithPlan($plan);
        $service = app(CouponService::class);
        $coupon = $service->save([
            'code' => 'VET20', 'percentage' => 20, 'active' => true,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(),
            'duration_months' => 1, 'plan_ids' => [],
        ], $operator);
        $service->apply($clinic->subscription, $coupon->code, $operator);

        try {
            $service->apply($clinic->subscription, $coupon->code, $operator);
            $this->fail('Uma segunda promoção sobreposta deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon_code', $exception->errors());
        }

        $this->actingAs($operator)->put(route('saas.subscriptions.update', $clinic), [
            'plan_id' => $otherPlan->id, 'status' => 'active', 'starts_at' => today()->toDateString(),
        ])->assertSessionHasErrors('plan_id');
    }

    public function test_onboarding_applies_coupon_and_exposes_commercial_management_pages(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        $plan = Plan::query()->where('slug', 'profissional')->firstOrFail();
        app(CouponService::class)->save([
            'code' => 'NOVO30', 'percentage' => 30, 'active' => true,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(),
            'duration_months' => 3, 'plan_ids' => [$plan->id],
        ], $operator);

        $payload = [
            'clinic' => [
                'corporate_name' => 'Pet Comercial Ltda', 'trade_name' => 'Pet Comercial',
                'business_type' => 'pet_shop', 'document_type' => 'cnpj', 'cnpj' => '12345678000199',
                'email' => 'pet@example.test', 'phone' => '21999999999',
            ],
            'plan_id' => $plan->id,
            'coupon_code' => 'novo30',
            'admin' => [
                'name' => 'Admin Cliente', 'email' => 'cliente@example.test',
                'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            ],
        ];

        $this->actingAs($operator)->post(route('saas.onboarding.store'), $payload)->assertSessionDoesntHaveErrors();
        $clinic = Clinic::query()->where('cnpj', '12345678000199')->firstOrFail();
        $this->assertDatabaseHas('saas_subscription_promotions', [
            'subscription_id' => $clinic->subscription->id, 'coupon_code' => 'NOVO30',
            'base_price_cents' => 19700, 'final_price_cents' => 13790,
        ]);
        $this->get(route('saas.coupons.index'))->assertOk()->assertSee('NOVO30');
        $this->get(route('saas.legal.index'))->assertOk()->assertSee('Minuta');
    }

    public function test_drafts_are_visible_but_cannot_be_accepted_until_every_document_is_published(): void
    {
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $clinic = $this->clinicWithPlan($plan);
        $admin = $this->userWithPermissions($clinic, ['users.manage']);

        $this->get(route('legal.show', 'terms-of-use'))
            ->assertOk()
            ->assertSee('MINUTA PARA REVISÃO JURÍDICA')
            ->assertSee('SEM VIGÊNCIA');

        $this->actingAs($admin)->get(route('legal.acceptance.show'))
            ->assertOk()
            ->assertSee('Pacote ainda não liberado para aceite')
            ->assertSee('disabled', false);
        $this->post(route('legal.acceptance.store'), [
            'acceptance_confirmed' => 1, 'authority_confirmed' => 1,
        ])->assertSessionHasErrors('acceptance_confirmed');
        $this->assertDatabaseCount('saas_legal_acceptances', 0);
    }

    public function test_published_package_creates_immutable_versioned_acceptance_evidence(): void
    {
        $operator = $this->userWithPermissions(null, ['saas.manage']);
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $clinic = $this->clinicWithPlan($plan);
        $admin = $this->userWithPermissions($clinic, ['users.manage']);
        $legal = app(LegalDocumentService::class);

        $seededDraft = LegalDocument::query()->firstOrFail();
        $this->actingAs($operator)->post(route('saas.legal.publish', $seededDraft), [
            'legal_review_confirmed' => 1, 'reviewer_name' => 'Dra. Revisora Teste',
        ])->assertSessionHasErrors('legal_review_confirmed');

        foreach (LegalDocument::TYPES as $type => $label) {
            $document = $legal->saveDraft([
                'type' => $type,
                'title' => $label.' VetFlow',
                'version' => '2026.11-reviewed',
                'body' => str_repeat('Conteúdo final revisado para contratação, proteção de dados, operação, suporte e evidência eletrônica. ', 5),
                'effective_at' => now(),
            ], $operator);
            $legal->publish($document, $operator, 'Dra. Revisora Teste');
        }

        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('legal.acceptance.store'), [
                'acceptance_confirmed' => 1, 'authority_confirmed' => 1,
            ], ['User-Agent' => 'VetFlow-Test/1.0'])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('saas_legal_acceptances', count(LegalDocument::TYPES));
        $acceptances = LegalAcceptance::query()->get();
        $this->assertCount(1, $acceptances->pluck('acceptance_uuid')->unique());
        $this->assertSame(9700, $acceptances->first()->base_price_cents);
        $this->assertSame(9700, $acceptances->first()->final_price_cents);
        $this->assertSame($plan->id, $acceptances->first()->plan_id);
        $this->assertSame(64, strlen($acceptances->first()->document_hash));
        $this->assertSame('203.0.113.10', $acceptances->first()->ip_address);
        $this->assertDatabaseHas('audit_events', ['event' => 'saas.legal-package.accepted', 'clinic_id' => $clinic->id]);

        $this->post(route('legal.acceptance.store'), [
            'acceptance_confirmed' => 1, 'authority_confirmed' => 1,
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('saas_legal_acceptances', count(LegalDocument::TYPES));

        $coupon = app(CouponService::class)->save([
            'code' => 'REACEITE10', 'percentage' => 10, 'active' => true,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(),
            'duration_months' => 1, 'plan_ids' => [$plan->id],
        ], $operator);
        app(CouponService::class)->apply($clinic->subscription, $coupon->code, $operator);
        $this->post(route('legal.acceptance.store'), [
            'acceptance_confirmed' => 1, 'authority_confirmed' => 1,
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('saas_legal_acceptances', count(LegalDocument::TYPES) * 2);
        $this->assertCount(2, LegalAcceptance::query()->pluck('acceptance_uuid')->unique());

        $this->expectException(LogicException::class);
        $acceptances->first()->update(['signer_name' => 'Alterado indevidamente']);
    }

    public function test_public_home_discloses_coupon_rules_and_all_legal_links(): void
    {
        $response = $this->get(route('home'));
        $response->assertOk()->assertSee('Cupons promocionais');
        foreach (array_keys(LegalDocument::TYPES) as $type) {
            $response->assertSee(route('legal.show', $type));
        }
    }

    private function clinicWithPlan(Plan $plan): Clinic
    {
        $number = str_pad((string) random_int(1, 99999999999999), 14, '0', STR_PAD_LEFT);
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica '.$number, 'trade_name' => 'Vet '.$number,
            'cnpj' => $number, 'business_type' => 'mixed', 'active' => true,
        ]);
        $clinic->subscription()->update([
            'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => today(),
        ]);

        return $clinic->fresh('subscription.plan');
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
            $role->permissions()->attach(Permission::query()->where('slug', $slug)->firstOrFail()->id);
        }
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }
}
