<?php

namespace App\Modules\Patients\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Model;
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
}
