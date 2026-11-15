<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalHomologationReview extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'schema_version' => 'integer',
        'evidence_snapshot' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
