<?php

namespace App\Modules\Audit\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
