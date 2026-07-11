<?php

namespace App\Modules\ProductIntelligence\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalProduct extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_CONFLICT = 'CONFLICT';

    protected $table = 'global_products';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'source_confidence' => 'decimal:2',
        'prescription_required' => 'boolean',
        'last_lookup_at' => 'datetime',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(GlobalProductSource::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(GlobalProductImage::class);
    }

    public function regulatoryData(): HasMany
    {
        return $this->hasMany(GlobalProductRegulatoryData::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
