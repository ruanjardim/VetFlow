<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLookupCatalog extends Model
{
    protected $table = 'product_lookup_catalogs';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'source_payload' => 'array',
        'last_lookup_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
