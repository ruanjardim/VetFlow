<?php

namespace App\Modules\ProductIntelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalProductRegulatoryData extends Model
{
    protected $table = 'global_product_regulatory_data';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'confidence' => 'decimal:2',
        'prescription_required' => 'boolean',
    ];

    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }
}
