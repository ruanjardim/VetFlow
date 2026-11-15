<?php

namespace App\Modules\Saas\Services;

use App\Models\User;
use App\Modules\Saas\Models\Feature;
use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Support\FeatureCatalog;
use Illuminate\Support\Facades\Schema;

class SubscriptionFeatureService
{
    /** @var array<string, Feature|null> */
    private array $featureCache = [];

    /** @var array<int, Subscription|null> */
    private array $subscriptionCache = [];

    public function value(?int $clinicId, string $featureKey): mixed
    {
        if ($clinicId === null || ! Schema::hasTable('saas_subscriptions')) {
            return $clinicId === null ? true : $this->safeDefault($featureKey);
        }

        if (! array_key_exists($featureKey, $this->featureCache)) {
            $this->featureCache[$featureKey] = Feature::query()->where('key', $featureKey)->first();
        }
        $feature = $this->featureCache[$featureKey];
        if (! $feature) {
            return false;
        }

        if (! array_key_exists($clinicId, $this->subscriptionCache)) {
            $this->subscriptionCache[$clinicId] = Subscription::query()
                ->with(['plan.featureValues.feature', 'overrides.feature'])
                ->where('clinic_id', $clinicId)
                ->first();
        }
        $subscription = $this->subscriptionCache[$clinicId];

        if (! $subscription?->grantsAccess()) {
            return $this->castValue($feature, $feature->default_value);
        }

        $override = $subscription->overrides->firstWhere('feature_id', $feature->id);
        if ($override) {
            return $this->castValue($feature, $override->value);
        }

        if ($featureKey === 'max_users' || $featureKey === 'max_units') {
            return $subscription->plan->getAttribute($featureKey);
        }

        $planValue = $subscription->plan->featureValues->firstWhere('feature_id', $feature->id);

        return $this->castValue($feature, $planValue?->value ?? $feature->default_value);
    }

    public function enabled(?int $clinicId, string $featureKey): bool
    {
        return filter_var($this->value($clinicId, $featureKey), FILTER_VALIDATE_BOOLEAN);
    }

    public function limit(?int $clinicId, string $featureKey): ?int
    {
        $value = $this->value($clinicId, $featureKey);

        return $value === null || $value === '' || $value === true ? null : max(0, (int) $value);
    }

    public function allowsPermission(User $user, string $permission): bool
    {
        $featureKey = FeatureCatalog::forPermission($permission);

        return $featureKey === null || $user->clinic_id === null || $this->enabled($user->clinic_id, $featureKey);
    }

    /** @return array{used: int, limit: ?int, available: ?int} */
    public function userUsage(?int $clinicId): array
    {
        if ($clinicId === null) {
            return ['used' => 0, 'limit' => null, 'available' => null];
        }

        $used = User::query()->where('clinic_id', $clinicId)->active()->count();
        $limit = $this->limit($clinicId, 'max_users');

        return [
            'used' => $used,
            'limit' => $limit,
            'available' => $limit === null ? null : max(0, $limit - $used),
        ];
    }

    private function safeDefault(string $featureKey): mixed
    {
        return str_starts_with($featureKey, 'max_') ? 0 : false;
    }

    private function castValue(Feature $feature, mixed $value): mixed
    {
        if ($feature->type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value === null || $value === '' ? null : (int) $value;
    }
}
