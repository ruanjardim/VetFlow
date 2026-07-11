<?php

namespace App\Modules\ProductIntelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalProductImage extends Model
{
    protected $table = 'global_product_images';

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'decimal:2',
        'is_primary' => 'boolean',
    ];

    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }
}
