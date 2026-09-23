<?php

namespace App\Modules\PetShopServices\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Modelo de pacote (catalogo): ex. "Clubinho M - 4 banhos em 30 dias". */
class PetshopPackage extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'petshop_packages';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'validity_days' => 'integer',
        'active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PetshopPackageItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function totalSessions(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
