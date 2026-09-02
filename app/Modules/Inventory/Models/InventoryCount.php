<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCount extends Model
{
    use BelongsToClinicTenant;

    public const STATUS_LABELS = [
        'draft' => 'Em contagem',
        'finalized' => 'Finalizada',
        'cancelled' => 'Cancelada',
    ];

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
