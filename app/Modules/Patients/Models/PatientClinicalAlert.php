<?php

namespace App\Modules\Patients\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientClinicalAlert extends Model
{
    use BelongsToClinicTenant;

    public const STATUS_LABELS = [
        'active' => 'Ativo',
        'resolved' => 'Resolvido',
    ];

    protected $guarded = [];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
