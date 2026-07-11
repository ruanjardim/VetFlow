<?php

namespace App\Modules\Schedules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use SoftDeletes;

    protected $table = 'schedules';

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
    ];
}
