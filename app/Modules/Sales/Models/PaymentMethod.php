<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToClinicTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payment method offered by a clinic: one row per card machine and type
 * ("Rede Crédito", "PagSeguro Débito"), plus cash, Pix, transfer and others.
 * The kind keeps the legacy sale_payments.method values, so reports that
 * group by kind keep working.
 */
class PaymentMethod extends Model
{
    use BelongsToClinicTenant;
    use SoftDeletes;

    public const KIND_LABELS = [
        'cash' => 'Dinheiro',
        'pix' => 'Pix',
        'debit_card' => 'Cartão de débito',
        'credit_card' => 'Cartão de crédito',
        'transfer' => 'Transferência',
        'other' => 'Outro',
    ];

    public const CARD_KINDS = ['debit_card', 'credit_card'];

    /**
     * How the card machine pays the installments of a credit sale: one per
     * month (the first after the settlement days) or all of them after the
     * settlement days (automatic anticipation).
     */
    public const INSTALLMENT_SETTLEMENT_LABELS = [
        'monthly' => 'Uma por mês, a cada 30 dias',
        'upfront' => 'Todas no prazo (antecipação)',
    ];

    public const MAX_INSTALLMENTS = 24;

    protected $table = 'payment_methods';

    protected $guarded = [];

    protected $casts = [
        'fee_percent' => 'decimal:2',
        'installment_fee_percent' => 'decimal:2',
        'settlement_days' => 'integer',
        'max_installments' => 'integer',
        'requires_reference' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? 'Outro';
    }

    public function isCard(): bool
    {
        return in_array($this->kind, self::CARD_KINDS, true);
    }

    public function isCash(): bool
    {
        return $this->kind === 'cash';
    }

    public function allowsInstallments(): bool
    {
        return $this->maxInstallments() > 1;
    }

    public function maxInstallments(): int
    {
        return $this->kind === 'credit_card'
            ? min(self::MAX_INSTALLMENTS, max(1, (int) $this->max_installments))
            : 1;
    }

    /**
     * Fee for the number of installments: the installment fee applies from
     * two installments on when it is configured.
     */
    public function feePercentFor(int $installments): float
    {
        if ($installments > 1 && $this->installment_fee_percent !== null) {
            return (float) $this->installment_fee_percent;
        }

        return (float) $this->fee_percent;
    }

    public function installmentSettlement(): string
    {
        $mode = $this->metadata['installment_settlement'] ?? 'monthly';

        return array_key_exists($mode, self::INSTALLMENT_SETTLEMENT_LABELS) ? $mode : 'monthly';
    }

    /**
     * Label of the reference field in the PDV: NSU for card machines, the
     * transaction id for Pix and a plain reference for the others.
     */
    public function referenceLabel(): string
    {
        return match (true) {
            $this->isCard() => 'NSU / autorização',
            $this->kind === 'pix' => 'ID da transação Pix',
            default => 'Referência',
        };
    }

    public function feeSummary(): string
    {
        $format = fn ($value) => number_format((float) $value, 2, ',', '.').'%';
        $summary = $format($this->fee_percent);

        if ($this->allowsInstallments() && $this->installment_fee_percent !== null) {
            $summary .= ' à vista · '.$format($this->installment_fee_percent).' parcelado';
        }

        return $summary;
    }

    /**
     * Compact data for the PDV and forms.
     *
     * @return array<string, mixed>
     */
    public function toPdvArray(): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'kind' => $this->kind,
            'acquirer' => $this->acquirer,
            'card_brand' => $this->card_brand,
            'max_installments' => $this->maxInstallments(),
            'requires_reference' => (bool) $this->requires_reference,
            'reference_label' => $this->referenceLabel(),
        ];
    }
}
