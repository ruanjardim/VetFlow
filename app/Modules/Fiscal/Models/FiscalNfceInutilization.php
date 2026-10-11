<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalNfceInutilization extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'series' => 'integer',
        'number_start' => 'integer',
        'number_end' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
