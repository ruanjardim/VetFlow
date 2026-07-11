<?php

namespace App\Modules\ClinicProducts\Models;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicProduct extends Model
{
    protected $table = 'clinic_products';

    protected $guarded = [];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'expires_at' => 'date',
        'active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }
}
