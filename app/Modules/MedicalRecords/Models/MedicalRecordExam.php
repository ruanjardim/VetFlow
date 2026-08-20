<?php

namespace App\Modules\MedicalRecords\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MedicalRecordExam extends Model
{
    protected $guarded = [];

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(AnimalExam::class, 'animal_exam_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(MedicalRecordExamResult::class);
    }
}
