<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer credit ledger (saldo credor). Positive entries add credit
 * (advance, change kept as credit, return or cancellation as credit),
 * negative ones use it (payment in a sale, credit given back in money).
 * The debit side (fiado) comes from sales with an outstanding balance.
 */
class CustomerCreditEntry extends Model
{
    use BelongsToClinicTenant;

    public const TYPE_LABELS = [
        'deposit' => 'Adiantamento',
        'change' => 'Troco guardado como crédito',
        'return' => 'Devolução em crédito',
        'cancellation' => 'Crédito devolvido por cancelamento',
        'sale_payment' => 'Usado em venda',
        'refund' => 'Crédito devolvido ao cliente',
    ];

    protected $table = 'customer_credit_entries';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function salePayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
