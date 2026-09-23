<?php

namespace App\Modules\PetShopServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PetPackageBalance extends Model
{
    protected $table = 'pet_package_balances';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_value' => 'decimal:2',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(PetPackage::class, 'pet_package_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PetPackageUsage::class);
    }

    public function remaining(): int
    {
        $used = $this->relationLoaded('usages')
            ? (int) $this->usages->sum('quantity')
            : (int) $this->usages()->sum('quantity');

        return max(0, $this->quantity - $used);
    }
}
