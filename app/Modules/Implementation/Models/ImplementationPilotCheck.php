<?php

namespace App\Modules\Implementation\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationPilotCheck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'completed' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
