<?php

namespace App\Modules\MedicalRecords\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalRecord extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'examined_at' => 'datetime',
        'weight' => 'decimal:2',
        'temperature' => 'decimal:1',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
