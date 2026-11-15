<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalProductionActivation extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'profile_version' => 'integer',
        'homologation_profile_snapshot' => 'array',
        'activated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function homologationReview(): BelongsTo
    {
        return $this->belongsTo(FiscalHomologationReview::class, 'homologation_review_id');
    }
}
