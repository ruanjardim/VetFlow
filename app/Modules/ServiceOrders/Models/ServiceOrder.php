<?php

namespace App\Modules\ServiceOrders\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'service_orders';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'closed_at' => 'datetime',
        'services_total' => 'decimal:2',
        'products_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }
}
