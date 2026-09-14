<?php

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $table = 'saas_plans';

    protected $fillable = [
        'name', 'slug', 'description', 'active', 'monthly_price', 'annual_price',
        'max_users', 'max_units', 'display_order', 'internal',
    ];

    protected $casts = [
        'active' => 'boolean',
        'internal' => 'boolean',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'max_users' => 'integer',
        'max_units' => 'integer',
        'display_order' => 'integer',
    ];

    public function featureValues(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
