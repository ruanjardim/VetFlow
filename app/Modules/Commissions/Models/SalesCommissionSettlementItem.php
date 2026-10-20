<?php

namespace App\Modules\Commissions\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCommissionSettlementItem extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected $casts = [
        'source_date' => 'date',
        'base_amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'revision' => 'integer',
        'snapshot' => 'array',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SalesCommissionSettlement::class, 'settlement_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id')->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class, 'sale_payment_id')->withTrashed();
    }
}
