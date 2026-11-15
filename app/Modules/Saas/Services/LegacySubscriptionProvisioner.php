<?php

namespace App\Modules\Saas\Services;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\Subscription;
use Illuminate\Support\Facades\Schema;

class LegacySubscriptionProvisioner
{
    public function ensure(Clinic $clinic): ?Subscription
    {
        if (! Schema::hasTable('saas_plans') || ! Schema::hasTable('saas_subscriptions')) {
            return null;
        }

        $legacyPlan = Plan::query()->where('internal', true)->where('active', true)->first();
        if (! $legacyPlan) {
            return null;
        }

        return Subscription::query()->firstOrCreate(
            ['clinic_id' => $clinic->id],
            ['plan_id' => $legacyPlan->id, 'status' => 'active', 'starts_at' => today()]
        );
    }
}
