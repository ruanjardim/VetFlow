<?php

namespace App\Modules\Saas\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPromotion extends Model
{
    protected $table = 'saas_subscription_promotions';

    protected $fillable = [
        'coupon_id', 'subscription_id', 'clinic_id', 'plan_id', 'coupon_code',
        'percentage', 'billing_cycle', 'base_price_cents', 'discount_cents',
        'final_price_cents', 'starts_on', 'ends_on', 'applied_at',
        'applied_by_user_id', 'cancelled_at', 'cancelled_by_user_id',
        'cancellation_reason',
    ];

    protected $casts = [
        'percentage' => 'integer',
        'base_price_cents' => 'integer',
        'discount_cents' => 'integer',
        'final_price_cents' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function scopeEffectiveOn(Builder $query, ?\DateTimeInterface $date = null): Builder
    {
        $date ??= today();

        return $query
            ->whereNull('cancelled_at')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }
}
