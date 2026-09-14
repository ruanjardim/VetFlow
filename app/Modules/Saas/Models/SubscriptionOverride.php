<?php

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionOverride extends Model
{
    protected $table = 'saas_subscription_overrides';

    protected $fillable = ['subscription_id', 'feature_id', 'value'];

    protected $casts = ['value' => 'json'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
