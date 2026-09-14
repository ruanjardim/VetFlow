<?php

namespace App\Modules\Saas\Models;

use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const STATUSES = ['trial', 'active', 'suspended', 'cancelled'];

    protected $table = 'saas_subscriptions';

    protected $fillable = [
        'clinic_id', 'plan_id', 'status', 'starts_at', 'ends_at', 'trial_ends_at', 'renews_at',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'trial_ends_at' => 'date',
        'renews_at' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(SubscriptionOverride::class);
    }

    public function grantsAccess(): bool
    {
        if (! in_array($this->status, ['trial', 'active'], true) || ! $this->plan?->active) {
            return false;
        }

        if ($this->starts_at?->gt(today()) || $this->ends_at?->lt(today())) {
            return false;
        }

        return $this->status !== 'trial' || ! $this->trial_ends_at?->lt(today());
    }
}
