<?php

namespace App\Modules\Financial\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReconciliation extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected $casts = [
        'reconciled_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialStatementEntry::class, 'financial_statement_entry_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }
}
