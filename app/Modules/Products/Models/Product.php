<?php

namespace App\Modules\Products\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'active' => 'boolean',
        'lookup_metadata' => 'array',
        'looked_up_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'minimum_stock');
    }
}
