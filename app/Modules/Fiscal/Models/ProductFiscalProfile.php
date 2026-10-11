<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFiscalProfile extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'ready' => 'boolean',
        'missing_fields' => 'array',
        'configuration_version' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
