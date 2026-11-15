<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\PetShopServices\Models\PetShopService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFiscalProfile extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'iss_rate' => 'decimal:4',
        'iss_withheld' => 'boolean',
        'ready' => 'boolean',
        'missing_fields' => 'array',
        'configuration_version' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PetShopService::class, 'petshop_service_id')->withTrashed();
    }
}
