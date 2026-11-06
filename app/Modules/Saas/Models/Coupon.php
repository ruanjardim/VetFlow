<?php

namespace App\Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use SoftDeletes;

    public const PERCENTAGES = [10, 20, 30, 40];

    protected $table = 'saas_coupons';

    protected $fillable = [
        'code', 'percentage', 'active', 'valid_from', 'valid_until',
        'duration_months', 'max_redemptions', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'percentage' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'duration_months' => 'integer',
        'max_redemptions' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $coupon->code = Str::upper(trim((string) $coupon->code));
        });
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'saas_coupon_plan', 'coupon_id', 'plan_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(SubscriptionPromotion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isAvailableAt(?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->active
            && $this->valid_from?->lte($moment)
            && $this->valid_until?->gte($moment);
    }
}
