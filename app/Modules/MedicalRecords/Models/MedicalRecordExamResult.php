<?php

namespace App\Modules\MedicalRecords\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordExamResult extends Model
{
    use BelongsToClinicTenant;

    public const STATUS_LABELS = [
        'draft' => 'Rascunho',
        'finalized' => 'Finalizado',
        'cancelled' => 'Cancelado',
    ];

    protected $guarded = [];

    protected $casts = [
        'collected_at' => 'datetime',
        'resulted_at' => 'datetime',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function examRequest(): BelongsTo
    {
        return $this->belongsTo(MedicalRecordExam::class, 'medical_record_exam_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }
}
