<?php

namespace App\Modules\PetShopServices\Models;

use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetPackageUsage extends Model
{
    protected $table = 'pet_package_usages';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_value' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(PetPackageBalance::class, 'pet_package_balance_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class)->withoutGlobalScopes();
    }
}
