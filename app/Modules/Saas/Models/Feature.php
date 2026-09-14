<?php

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    protected $table = 'saas_features';

    protected $fillable = ['key', 'name', 'description', 'type', 'default_value', 'display_order'];

    protected $casts = ['default_value' => 'json', 'display_order' => 'integer'];

    public function planValues(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(SubscriptionOverride::class);
    }
}
