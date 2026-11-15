<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalDocument extends Model
{
    use BelongsToClinicTenant;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'profile_version' => 'integer',
        'schema_version' => 'integer',
        'issuer_snapshot' => 'array',
        'recipient_snapshot' => 'array',
        'items_snapshot' => 'array',
        'payments_snapshot' => 'array',
        'totals_snapshot' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
        'authorized_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'webhook_resend_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function saleAllocation(): BelongsTo
    {
        return $this->belongsTo(FiscalSaleAllocation::class, 'fiscal_sale_allocation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FiscalDocumentEvent::class)->orderByDesc('occurred_at');
    }
}
