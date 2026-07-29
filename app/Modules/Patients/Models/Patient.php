<?php

namespace App\Modules\Patients\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'patients';

    protected $guarded = [];

    protected $casts = [
        'birth_date' => 'date',
        'weight' => 'decimal:2',
    ];

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }
}
