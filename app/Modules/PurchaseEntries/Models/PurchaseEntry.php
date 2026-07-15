<?php

namespace App\Modules\PurchaseEntries\Models;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseEntry extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_entries';

    protected $guarded = [];

    protected $casts = [
        'purchased_at' => 'datetime',
        'received_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseEntryItem::class);
    }

    public function financialTransaction(): HasOne
    {
        return $this->hasOne(FinancialTransaction::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
