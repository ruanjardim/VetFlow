<?php

namespace App\Modules\Commissions\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Financial\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCommissionSettlement extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'sales_count' => 'integer',
        'entries_count' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesCommissionSettlementItem::class, 'settlement_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
