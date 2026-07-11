<?php

namespace App\Modules\Sales\Models;

use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\ServiceOrders\Models\ServiceOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends Model
{
    use SoftDeletes;

    protected $table = 'sale_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'cost_unit_price' => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'gross_profit_total' => 'decimal:2',
        'gross_margin_percent' => 'decimal:2',
        'returned_quantity' => 'decimal:3',
        'refunded_total' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function petShopService(): BelongsTo
    {
        return $this->belongsTo(PetShopService::class);
    }

    public function serviceOrderItem(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SaleEvent::class);
    }
}
