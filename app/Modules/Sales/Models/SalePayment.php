<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalePayment extends Model
{
    use SoftDeletes;

    protected $table = 'sale_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'installments' => 'integer',
        'payment_method_id' => 'integer',
        'paid_at' => 'datetime',
        'expected_settlement_date' => 'date',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The clinic method used. Deactivated or removed methods still show on
     * old receipts.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }

    public function kindLabel(): string
    {
        return PaymentMethod::KIND_LABELS[$this->method] ?? 'Outro';
    }

    /**
     * Name shown on receipts and reports: the clinic method ("Rede Visa
     * Crédito") or, for payments recorded before the methods existed, the
     * kind.
     */
    public function methodLabel(): string
    {
        return $this->payment_method_id && $this->paymentMethod
            ? $this->paymentMethod->name
            : $this->kindLabel();
    }

    public function netAmount(): float
    {
        return $this->net_amount !== null
            ? (float) $this->net_amount
            : round((float) $this->amount - (float) $this->fee_amount, 2);
    }
}
