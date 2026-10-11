<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalReturnReview extends Model
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
