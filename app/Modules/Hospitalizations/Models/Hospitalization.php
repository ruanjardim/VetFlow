<?php

namespace App\Modules\Hospitalizations\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\MedicalRecords\Models\MedicalRecord;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hospitalization extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'admitted_at' => 'datetime',
        'expected_discharge_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }
}
