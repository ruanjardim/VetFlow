<?php

namespace App\Modules\ServiceOrders\Models;

use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderItem extends Model
{
    use SoftDeletes;

    protected $table = 'service_order_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function petShopService(): BelongsTo
    {
        return $this->belongsTo(PetShopService::class);
    }
}
