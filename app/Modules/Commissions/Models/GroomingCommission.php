<?php

namespace App\Modules\Commissions\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lancamento de comissao do profissional de banho e tosa. */
class GroomingCommission extends Model
{
    use BelongsToClinicTenant;

    public const STATUS_LABELS = [
        'pending' => 'A pagar',
        'settled' => 'Fechada',
        'cancelled' => 'Cancelada',
    ];

    protected $table = 'grooming_commissions';

    protected $guarded = [];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'earned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(GroomingCommissionSettlement::class, 'settlement_id');
    }
}
