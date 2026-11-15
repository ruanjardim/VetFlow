<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalProfile extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'nfce_enabled' => 'boolean',
        'nfse_enabled' => 'boolean',
        'certificate_expires_at' => 'datetime',
        'configuration_version' => 'integer',
        'metadata' => 'array',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FiscalDocument::class, 'clinic_id', 'clinic_id');
    }
}
