<?php

namespace App\Modules\Saas\Services;

use App\Modules\Saas\Models\Subscription;
use App\Modules\Saas\Models\SubscriptionPromotion;
use Illuminate\Support\Facades\Schema;

class SubscriptionPriceService
{
    /**
     * @return array{
     *   billing_cycle: string,
     *   base_price_cents: int|null,
     *   discount_cents: int,
     *   final_price_cents: int|null,
     *   coupon_code: string|null,
     *   discount_percentage: int|null,
     *   promotion: SubscriptionPromotion|null
     * }
     */
    public function quote(Subscription $subscription, ?\DateTimeInterface $date = null): array
    {
        $subscription->loadMissing('plan');
        $basePrice = $subscription->plan?->monthly_price;
        $basePriceCents = $basePrice === null ? null : (int) round(((float) $basePrice) * 100);
        $promotion = null;

        if ($basePriceCents !== null && Schema::hasTable('saas_subscription_promotions')) {
            $promotion = $subscription->promotions()
                ->effectiveOn($date)
                ->where('plan_id', $subscription->plan_id)
                ->latest('applied_at')
                ->first();
        }

        return [
            'billing_cycle' => 'monthly',
            'base_price_cents' => $basePriceCents,
            'discount_cents' => $promotion?->discount_cents ?? 0,
            'final_price_cents' => $promotion?->final_price_cents ?? $basePriceCents,
            'coupon_code' => $promotion?->coupon_code,
            'discount_percentage' => $promotion?->percentage,
            'promotion' => $promotion,
        ];
    }

    public static function format(?int $cents): string
    {
        return $cents === null ? '—' : 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
