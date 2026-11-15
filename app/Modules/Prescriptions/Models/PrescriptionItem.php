<?php

namespace App\Modules\Prescriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $guarded = [];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
