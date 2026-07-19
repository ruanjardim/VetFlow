<?php

namespace App\Modules\Schedules\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'schedules';

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }
}
