<?php

namespace App\Modules\ProductIntelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalProductSource extends Model
{
    protected $table = 'global_product_sources';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'confidence' => 'decimal:2',
        'queried_at' => 'datetime',
    ];

    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }
}
