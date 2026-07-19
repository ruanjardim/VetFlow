<?php

namespace App\Modules\Schedules\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Model;
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
}
