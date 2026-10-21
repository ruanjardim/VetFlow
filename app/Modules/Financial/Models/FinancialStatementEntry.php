<?php

namespace App\Modules\Financial\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinancialStatementEntry extends Model
{
    use BelongsToClinicTenant;

    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(FinancialStatementImport::class, 'financial_statement_import_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class);
    }

    public function activeReconciliation(): HasOne
    {
        return $this->hasOne(FinancialReconciliation::class)->where('status', 'confirmed')->latestOfMany();
    }
}
