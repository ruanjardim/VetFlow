<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\PurchaseEntries\Models\PurchaseEntryItem;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovement extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'inventory_movements';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'balance_before' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'occurred_at' => 'datetime',
        'expires_at' => 'date',
        'metadata' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function purchaseEntry(): BelongsTo
    {
        return $this->belongsTo(PurchaseEntry::class);
    }

    public function purchaseEntryItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseEntryItem::class);
    }

    public function isSystemManaged(): bool
    {
        return in_array($this->source, [
            'purchase_entry',
            'sale',
            'sale_return',
            'sale_cancellation',
        ], true);
    }
}
