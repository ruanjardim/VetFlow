<?php

namespace App\Modules\Saas\Services;

use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Saas\Models\Feature;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\PlanFeature;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\SubscriptionOverride;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasAdministrationService
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /** @return array<string, mixed> */
    public function dashboardData(): array
    {
        return [
            'activePlans' => Plan::query()->where('active', true)->where('internal', false)->count(),
            'activeClinics' => Clinic::query()->active()->count(),
            'activeSubscriptions' => Subscription::query()->whereIn('status', ['active', 'trial'])->count(),
            'activeUsers' => User::query()->active()->whereNotNull('clinic_id')->count(),
            'subscriptionsByStatus' => Subscription::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'recentClinics' => Clinic::query()->with('subscription.plan')->latest()->limit(8)->get(),
        ];
    }

    public function paginatePlans(): LengthAwarePaginator
    {
        return Plan::query()->withCount('subscriptions')->orderBy('internal')->orderBy('display_order')->orderBy('name')->paginate(20);
    }

    public function paginateClinics(): LengthAwarePaginator
    {
        return Clinic::query()->with(['subscription.plan'])->withCount(['children', 'subscription as subscription_count'])->orderBy('trade_name')->paginate(25);
    }

    /** @return array{features: Collection} */
    public function planFormData(): array
    {
        return ['features' => Feature::query()->where('type', 'boolean')->orderBy('display_order')->get()];
    }

    public function savePlan(array $data, User $actor, ?Plan $plan = null): Plan
    {
        return DB::transaction(function () use ($data, $actor, $plan): Plan {
            $creating = $plan === null;
            $plan ??= new Plan;
            $before = $plan->exists ? $this->planSnapshot($plan) : [];
            $plan->fill(collect($data)->only([
                'name', 'slug', 'description', 'active', 'monthly_price', 'annual_price',
                'max_users', 'max_units', 'display_order',
            ])->all());
            $plan->internal = $plan->exists ? $plan->internal : false;
            $plan->display_order ??= 0;
            $plan->save();

            $selected = collect($data['features'] ?? [])->map(fn ($value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
            Feature::query()->where('type', 'boolean')->get()->each(function (Feature $feature) use ($plan, $selected): void {
                PlanFeature::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_id' => $feature->id],
                    ['value' => (bool) $selected->get($feature->key, false)]
                );
            });

            $plan->load('featureValues.feature');
            $this->audit->record(
                $creating ? 'saas.plan.created' : 'saas.plan.updated',
                $plan,
                $before,
                $this->planSnapshot($plan),
                $actor,
                [],
                null,
                $plan->name
            );

            return $plan;
        });
    }

    /** @return array<string, mixed> */
    public function establishmentData(Clinic $clinic): array
    {
        $clinic->load(['subscription.plan.featureValues.feature', 'subscription.overrides.feature', 'children']);

        return [
            'clinic' => $clinic,
            'plans' => Plan::query()->where('active', true)->orderBy('internal')->orderBy('display_order')->get(),
            'features' => Feature::query()->orderBy('display_order')->get(),
            'activeUsers' => User::query()->where('clinic_id', $clinic->id)->active()->count(),
        ];
    }

    public function updateSubscription(Clinic $clinic, array $data, User $actor): Subscription
    {
        return DB::transaction(function () use ($clinic, $data, $actor): Subscription {
            $subscription = $clinic->subscription()->firstOrNew();
            $before = $subscription->exists ? $this->subscriptionSnapshot($subscription) : [];
            $subscription->fill(collect($data)->only(['plan_id', 'status', 'starts_at', 'ends_at', 'trial_ends_at', 'renews_at'])->all());
            $subscription->save();
            $this->syncOverrides($subscription, $data['overrides'] ?? []);
            $subscription->load(['plan', 'overrides.feature']);

            $this->audit->record(
                'saas.subscription.updated',
                $subscription,
                $before,
                $this->subscriptionSnapshot($subscription),
                $actor,
                [],
                $clinic->id,
                $clinic->trade_name ?: $clinic->corporate_name
            );

            return $subscription;
        });
    }

    /** @return array{plans: Collection, features: Collection, businessTypes: array<string, string>} */
    public function onboardingFormData(): array
    {
        return [
            'plans' => Plan::query()->where('active', true)->where('internal', false)->orderBy('display_order')->get(),
            'features' => Feature::query()->orderBy('display_order')->get(),
            'businessTypes' => [
                'veterinary_clinic' => 'Clínica veterinária', 'pet_shop' => 'Pet shop',
                'feed_store' => 'Casa de ração', 'grooming' => 'Banho e tosa',
                'mixed' => 'Operação mista', 'other' => 'Outro',
            ],
        ];
    }

    public function onboard(array $data, User $actor): Clinic
    {
        return DB::transaction(function () use ($data, $actor): Clinic {
            $role = Role::query()->whereNull('clinic_id')->where('slug', 'administrador')->active()->first();
            if (! $role) {
                throw ValidationException::withMessages(['admin.name' => 'O perfil Administrador não está configurado. Execute a carga de autorizações.']);
            }

            $clinic = Clinic::query()->create([
                ...$data['clinic'],
                'timezone' => $data['clinic']['timezone'] ?? 'America/Sao_Paulo',
                'currency' => 'BRL',
                'language' => 'pt_BR',
                'active' => true,
            ]);

            $subscription = $clinic->subscription()->firstOrNew();
            $subscription->fill([
                'plan_id' => $data['plan_id'], 'status' => 'active', 'starts_at' => today(),
                'ends_at' => null, 'trial_ends_at' => null, 'renews_at' => null,
            ]);
            $subscription->save();
            $this->syncOverrides($subscription, $data['overrides'] ?? []);

            $admin = User::query()->create([
                'clinic_id' => $clinic->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'phone' => $data['admin']['phone'] ?? null,
                'position' => 'Administrador',
                'password' => $data['admin']['password'],
                'active' => true,
            ]);
            DB::table('user_roles')->insert([
                'ulid' => (string) Str::ulid(), 'user_id' => $admin->id, 'role_id' => $role->id,
                'created_by' => $actor->id, 'updated_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->audit->record(
                'saas.tenant.onboarded',
                $clinic,
                [],
                ['clinic' => $clinic->trade_name, 'plan_id' => (int) $data['plan_id'], 'admin_email' => $admin->email],
                $actor,
                [],
                $clinic->id,
                $clinic->trade_name
            );

            return $clinic->load(['subscription.plan']);
        });
    }

    /** @param array<string, mixed> $values */
    private function syncOverrides(Subscription $subscription, array $values): void
    {
        $features = Feature::query()->whereIn('key', array_keys($values))->get()->keyBy('key');
        $keptIds = [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '' || ! $features->has($key)) {
                continue;
            }

            $feature = $features->get($key);
            $normalized = $feature->type === 'boolean'
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : max(0, (int) $value);
            $override = SubscriptionOverride::query()->updateOrCreate(
                ['subscription_id' => $subscription->id, 'feature_id' => $feature->id],
                ['value' => $normalized]
            );
            $keptIds[] = $override->id;
        }

        $subscription->overrides()->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))->delete();
    }

    /** @return array<string, mixed> */
    private function planSnapshot(Plan $plan): array
    {
        $plan->loadMissing('featureValues.feature');

        return [
            'name' => $plan->name, 'slug' => $plan->slug, 'active' => (bool) $plan->active,
            'monthly_price' => $plan->monthly_price, 'annual_price' => $plan->annual_price,
            'max_users' => $plan->max_users, 'max_units' => $plan->max_units,
            'features' => $plan->featureValues->mapWithKeys(fn (PlanFeature $item) => [$item->feature->key => $item->value])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionSnapshot(Subscription $subscription): array
    {
        $subscription->loadMissing(['plan', 'overrides.feature']);

        return [
            'plan' => $subscription->plan?->slug, 'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toDateString(), 'ends_at' => $subscription->ends_at?->toDateString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(), 'renews_at' => $subscription->renews_at?->toDateString(),
            'overrides' => $subscription->overrides->mapWithKeys(fn (SubscriptionOverride $item) => [$item->feature->key => $item->value])->all(),
        ];
    }
}
