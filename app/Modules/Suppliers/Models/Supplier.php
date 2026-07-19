<?php

namespace App\Modules\Suppliers\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'suppliers';

    protected $guarded = [];

    protected $casts = [
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
