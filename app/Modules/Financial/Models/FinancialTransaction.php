<?php

namespace App\Modules\Financial\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\PurchaseEntries\Models\PurchaseEntry;
use App\Modules\Sales\Models\Sale;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialTransaction extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    protected $table = 'financial_transactions';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'installment_number' => 'integer',
        'installment_total' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseEntry(): BelongsTo
    {
        return $this->belongsTo(PurchaseEntry::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'financial_transaction_id');
    }
}
