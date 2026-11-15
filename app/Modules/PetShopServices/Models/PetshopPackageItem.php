<?php

namespace App\Modules\PetShopServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetshopPackageItem extends Model
{
    protected $table = 'petshop_package_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(PetShopService::class, 'petshop_service_id')->withTrashed();
    }
}
