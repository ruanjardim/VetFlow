<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\SalePayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Payment methods per clinic and the snapshot each sale payment keeps:
 * kind, card machine, fee, net amount and expected settlement date.
 */
class PaymentMethodService
{
    /**
     * Created the first time a clinic uses the PDV, so nothing changes for
     * clinics that never configure their card machines.
     */
    public const DEFAULTS = [
        ['name' => 'Dinheiro', 'kind' => 'cash', 'settlement_days' => 0, 'max_installments' => 1],
        ['name' => 'Pix', 'kind' => 'pix', 'settlement_days' => 0, 'max_installments' => 1],
        ['name' => 'Cartão de débito', 'kind' => 'debit_card', 'settlement_days' => 1, 'max_installments' => 1],
        ['name' => 'Cartão de crédito', 'kind' => 'credit_card', 'settlement_days' => 30, 'max_installments' => 12],
        ['name' => 'Transferência', 'kind' => 'transfer', 'settlement_days' => 0, 'max_installments' => 1],
        ['name' => 'Outro', 'kind' => 'other', 'settlement_days' => 0, 'max_installments' => 1],
    ];

    public const INSTALLMENT_INTERVAL_DAYS = 30;

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Active methods of the clinic, creating the defaults when the clinic
     * has none yet. Without a clinic (global user with no clinic selected)
     * the result is empty.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function activeForClinic(?int $clinicId): Collection
    {
        return $this->forClinic($clinicId)->where('active', true)->values();
    }

    /**
     * Every method of the clinic, active or not, in display order.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function forClinic(?int $clinicId): Collection
    {
        if (! $clinicId) {
            return collect();
        }

        $this->ensureDefaults($clinicId);

        return PaymentMethod::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->ordered()
            ->get();
    }

    /**
     * Active methods for every clinic the user can sell for (the PDV of a
     * global user filters them by the selected clinic).
     *
     * @param iterable<int, int> $clinicIds
     * @return Collection<int, PaymentMethod>
     */
    public function activeForClinics(iterable $clinicIds): Collection
    {
        return collect($clinicIds)
            ->flatMap(fn ($clinicId) => $this->activeForClinic((int) $clinicId))
            ->values();
    }

    public function ensureDefaults(int $clinicId): void
    {
        $exists = PaymentMethod::query()
            ->withoutGlobalScope('clinic_tenant')
            ->withTrashed()
            ->where('clinic_id', $clinicId)
            ->exists();

        if ($exists) {
            return;
        }

        foreach (self::DEFAULTS as $index => $definition) {
            $method = new PaymentMethod($definition + [
                'fee_percent' => 0,
                'requires_reference' => false,
                'active' => true,
                'sort_order' => ($index + 1) * 10,
                'metadata' => ['default' => true],
            ]);
            $method->clinic_id = $clinicId;
            $method->saveQuietly();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $clinicId, array $data): PaymentMethod
    {
        $this->ensureDefaults($clinicId);

        $method = new PaymentMethod($this->normalize($data));
        $method->clinic_id = $clinicId;

        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            $method->sort_order = (int) PaymentMethod::query()
                ->withoutGlobalScope('clinic_tenant')
                ->withTrashed()
                ->where('clinic_id', $clinicId)
                ->max('sort_order') + 10;
        }

        $method->save();

        return $method;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        $metadata = $method->metadata ?? [];
        $normalized = $this->normalize($data, $metadata);

        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            unset($normalized['sort_order']);
        }

        $method->fill($normalized);
        $method->save();

        return $method;
    }

    public function findForClinic(int $clinicId, int $id): PaymentMethod
    {
        return PaymentMethod::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->findOrFail($id);
    }

    /**
     * The clinic method used for a payment that only informs a kind (legacy
     * forms and API callers): the first active method of that kind.
     */
    public function defaultForKind(?int $clinicId, string $kind): ?PaymentMethod
    {
        return $this->activeForClinic($clinicId)->firstWhere('kind', $kind);
    }

    public function find(?int $clinicId, mixed $paymentMethodId): ?PaymentMethod
    {
        if (! $clinicId || ! $paymentMethodId || ! is_numeric($paymentMethodId)) {
            return null;
        }

        return PaymentMethod::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->find((int) $paymentMethodId);
    }

    /**
     * Normalizes a sale payment row: resolves the clinic method (by id or by
     * kind), clamps the installments and snapshots fee, net amount and the
     * expected settlement date of the first installment. Payments that are
     * not received yet get no settlement date.
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public function snapshot(?int $clinicId, array $payment): array
    {
        $method = $this->find($clinicId, $payment['payment_method_id'] ?? null);

        if (! $method && ! empty($payment['method'])) {
            $method = $this->defaultForKind($clinicId, (string) $payment['method']);
        }

        $amount = round((float) ($payment['amount'] ?? 0), 2);
        $installments = max(1, (int) ($payment['installments'] ?? 1));
        $paidAt = ! empty($payment['paid_at']) ? Carbon::parse($payment['paid_at']) : null;

        if (! $method) {
            return array_merge($payment, [
                'payment_method_id' => null,
                'installments' => $installments,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'expected_settlement_date' => $paidAt?->toDateString(),
            ]);
        }

        $installments = min($installments, $method->maxInstallments());
        $fee = round($amount * $method->feePercentFor($installments) / 100, 2);

        return array_merge($payment, [
            'payment_method_id' => $method->id,
            'method' => $method->kind,
            'acquirer' => $method->acquirer ?: (($payment['acquirer'] ?? null) ?: null),
            'card_brand' => $method->isCard()
                ? ((($payment['card_brand'] ?? null) ?: $method->card_brand) ?: null)
                : null,
            'installments' => $installments,
            'fee_amount' => $fee,
            'net_amount' => round($amount - $fee, 2),
            'expected_settlement_date' => $paidAt
                ?->copy()
                ->addDays((int) $method->settlement_days)
                ->toDateString(),
        ]);
    }

    /**
     * Expected card settlements between two dates, one row per installment.
     *
     * @return array<string, mixed>
     */
    public function receivables(?string $from = null, ?string $to = null, ?int $paymentMethodId = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : today()->startOfDay();
        $end = $to ? Carbon::parse($to)->endOfDay() : today()->addDays(60)->endOfDay();
        $earliestFirstInstallment = $start->copy()
            ->subDays(self::INSTALLMENT_INTERVAL_DAYS * PaymentMethod::MAX_INSTALLMENTS);

        $payments = SalePayment::query()
            ->with(['sale', 'paymentMethod'])
            ->whereIn('method', PaymentMethod::CARD_KINDS)
            ->where('status', 'paid')
            ->whereNotNull('expected_settlement_date')
            ->whereDate('expected_settlement_date', '<=', $end->toDateString())
            ->whereDate('expected_settlement_date', '>=', $earliestFirstInstallment->toDateString())
            ->whereHas('sale', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))
            ->get();

        $rows = $payments
            ->flatMap(fn (SalePayment $payment) => $this->installmentSchedule($payment))
            ->filter(fn (array $row) => $row['date']->betweenIncluded($start, $end))
            ->sortBy(fn (array $row) => $row['date']->format('Ymd').'-'.str_pad((string) $row['payment_id'], 10, '0', STR_PAD_LEFT).'-'.str_pad((string) $row['number'], 3, '0', STR_PAD_LEFT))
            ->values();

        $byMethod = $rows
            ->groupBy('method_label')
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => $group->count(),
                'gross' => round((float) $group->sum('gross'), 2),
                'fee' => round((float) $group->sum('fee'), 2),
                'net' => round((float) $group->sum('net'), 2),
            ])
            ->sortByDesc('net')
            ->values();

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'stats' => [
                'count' => $rows->count(),
                'gross' => round((float) $rows->sum('gross'), 2),
                'fee' => round((float) $rows->sum('fee'), 2),
                'net' => round((float) $rows->sum('net'), 2),
            ],
            'by_method' => $byMethod,
            'by_date' => $rows->groupBy(fn (array $row) => $row['date']->toDateString()),
            'rows' => $rows,
        ];
    }

    /**
     * Splits a card payment into the installments the card machine pays.
     * Cents that do not divide evenly go to the last installment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function installmentSchedule(SalePayment $payment): array
    {
        $installments = max(1, (int) $payment->installments);
        $first = ($payment->expected_settlement_date ?? $payment->paid_at)?->copy()->startOfDay();

        if (! $first) {
            return [];
        }

        $upfront = $payment->paymentMethod?->installmentSettlement() === 'upfront';
        $amountCents = (int) round((float) $payment->amount * 100);
        $feeCents = (int) round((float) $payment->fee_amount * 100);
        $rows = [];

        for ($number = 1; $number <= $installments; $number++) {
            $isLast = $number === $installments;
            $gross = intdiv($amountCents, $installments) + ($isLast ? $amountCents % $installments : 0);
            $fee = intdiv($feeCents, $installments) + ($isLast ? $feeCents % $installments : 0);

            $rows[] = [
                'payment_id' => $payment->id,
                'sale_id' => $payment->sale_id,
                'sale_code' => $payment->sale?->code,
                'method_label' => $payment->methodLabel(),
                'acquirer' => $payment->acquirer,
                'card_brand' => $payment->card_brand,
                'reference' => $payment->reference ?: $payment->transaction_reference,
                'paid_at' => $payment->paid_at,
                'number' => $number,
                'installments' => $installments,
                'date' => $upfront
                    ? $first->copy()
                    : $first->copy()->addDays(self::INSTALLMENT_INTERVAL_DAYS * ($number - 1)),
                'gross' => $gross / 100,
                'fee' => $fee / 100,
                'net' => ($gross - $fee) / 100,
            ];
        }

        return $rows;
    }

    /**
     * Clinic used to resolve methods: the logged clinic or the one informed
     * by a global user.
     */
    public function clinicIdFor(mixed $informedClinicId = null): ?int
    {
        $clinicId = $this->tenant->clinicId() ?? ($informedClinicId ? (int) $informedClinicId : null);

        return $clinicId ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function normalize(array $data, array $metadata = []): array
    {
        $kind = array_key_exists($data['kind'] ?? null, PaymentMethod::KIND_LABELS) ? $data['kind'] : 'other';
        $isCredit = $kind === 'credit_card';
        $isCard = in_array($kind, PaymentMethod::CARD_KINDS, true);
        $maxInstallments = $isCredit
            ? min(PaymentMethod::MAX_INSTALLMENTS, max(1, (int) ($data['max_installments'] ?? 1)))
            : 1;
        $installmentFee = $data['installment_fee_percent'] ?? null;

        if ($isCredit && $maxInstallments > 1) {
            $metadata['installment_settlement'] = array_key_exists($data['installment_settlement'] ?? null, PaymentMethod::INSTALLMENT_SETTLEMENT_LABELS)
                ? $data['installment_settlement']
                : 'monthly';
        } else {
            unset($metadata['installment_settlement']);
        }

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'kind' => $kind,
            'acquirer' => filled($data['acquirer'] ?? null) ? trim((string) $data['acquirer']) : null,
            'card_brand' => $isCard && filled($data['card_brand'] ?? null) ? trim((string) $data['card_brand']) : null,
            'fee_percent' => round((float) ($data['fee_percent'] ?? 0), 2),
            'installment_fee_percent' => $isCredit && $maxInstallments > 1 && $installmentFee !== null && $installmentFee !== ''
                ? round((float) $installmentFee, 2)
                : null,
            'settlement_days' => max(0, (int) ($data['settlement_days'] ?? 0)),
            'max_installments' => $maxInstallments,
            'requires_reference' => (bool) ($data['requires_reference'] ?? false),
            'active' => (bool) ($data['active'] ?? true),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'metadata' => $metadata === [] ? null : $metadata,
        ];
    }
}
