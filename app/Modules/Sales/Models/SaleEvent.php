<?php

namespace App\Modules\Sales\Models;

use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleEvent extends Model
{
    protected $table = 'sale_events';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
