<?php

namespace App\Modules\PetShopServices\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PetShopService extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'petshop_services';

    protected $guarded = [];

    protected $casts = [
        'base_price' => 'decimal:2',
        'small_price' => 'decimal:2',
        'medium_price' => 'decimal:2',
        'large_price' => 'decimal:2',
        'giant_price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'requires_appointment' => 'boolean',
        'active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
