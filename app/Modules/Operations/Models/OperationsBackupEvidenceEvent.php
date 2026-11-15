<?php

namespace App\Modules\Operations\Models;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationsBackupEvidenceEvent extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
