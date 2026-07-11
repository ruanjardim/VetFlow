<?php

namespace App\Modules\ProductIntelligence\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalProductSuggestion extends Model
{
    protected $table = 'global_product_suggestions';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'confidence' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];
}
