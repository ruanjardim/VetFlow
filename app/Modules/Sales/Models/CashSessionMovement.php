<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use App\Models\User;
use App\Modules\Financial\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money moved inside a cash session outside of the sale receipts: supply
 * (suprimento), withdrawal (sangria), expense paid from the drawer (despesa),
 * sale refunds, and customer credit received (advance, change kept as
 * credit) or given back.
 */
class CashSessionMovement extends Model
{
    use BelongsToClinicTenant;

    public const TYPE_LABELS = [
        'supply' => 'Suprimento',
        'withdrawal' => 'Sangria',
        'expense' => 'Despesa',
        'refund' => 'Estorno de venda',
        'credit_deposit' => 'Crédito de cliente recebido',
        'credit_refund' => 'Crédito devolvido ao cliente',
    ];

    /** Types the operator registers by hand. */
    public const MANUAL_TYPES = ['supply', 'withdrawal', 'expense'];

    /** Types that take money out of the session. */
    public const OUTGOING_TYPES = ['withdrawal', 'expense', 'refund', 'credit_refund'];

    protected $table = 'cash_session_movements';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
        'payment_method_id' => 'integer',
    ];

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function isOutgoing(): bool
    {
        return in_array($this->type, self::OUTGOING_TYPES, true);
    }

    /**
     * Signed effect on the session: supplies add, the others subtract.
     */
    public function signedAmount(): float
    {
        return $this->isOutgoing() ? -1 * (float) $this->amount : (float) $this->amount;
    }
}
