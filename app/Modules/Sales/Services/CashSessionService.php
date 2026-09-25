<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Sales\Models\CashSession;
use App\Modules\Sales\Models\CashSessionMovement;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cash sessions per operator (caixa): opening with a change float, supplies,
 * withdrawals and expenses, the expected amounts per payment method, the
 * operator closing with the counted values, the card fees posted as one
 * expense per card machine, and the manager review.
 */
class CashSessionService
{
    public const CODE_PREFIX = 'CX-';

    public function __construct(private readonly PaymentMethodService $paymentMethods) {}

    /**
     * The open session of the user in the clinic. Sessions left open from a
     * previous day are closed first (see closeStaleSessions()).
     */
    public function currentFor(?User $user, ?int $clinicId): ?CashSession
    {
        if (! $user || ! $clinicId) {
            return null;
        }

        $this->closeStaleSessions($clinicId);

        return $this->query()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $user->id)
            ->open()
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open sessions of the user keyed by clinic, for the PDV of a global
     * user who sells for more than one clinic.
     *
     * @param  iterable<int, int>  $clinicIds
     * @return array<int, CashSession>
     */
    public function currentForClinics(?User $user, iterable $clinicIds): array
    {
        $sessions = [];

        foreach ($clinicIds as $clinicId) {
            $session = $this->currentFor($user, (int) $clinicId);

            if ($session) {
                $sessions[(int) $clinicId] = $session;
            }
        }

        return $sessions;
    }

    /**
     * Throws a validation error when the user has no open session in the
     * clinic: receiving money requires an open cash session.
     */
    public function requireOpenSession(?User $user, ?int $clinicId, string $field = 'cash_session', ?string $message = null): CashSession
    {
        $session = $this->currentFor($user, $clinicId);

        if (! $session) {
            throw ValidationException::withMessages([
                $field => $message ?? 'Abra o seu caixa antes de receber. No PDV, use "Abrir caixa" e informe o fundo de troco.',
            ]);
        }

        return $session;
    }

    /**
     * Suggested change float: what the operator (or, for a first session,
     * the clinic) left in the drawer when closing the previous session.
     */
    public function suggestedOpening(?User $user, int $clinicId): float
    {
        $closed = $this->query()
            ->where('clinic_id', $clinicId)
            ->whereIn('status', ['closed', 'reviewed'])
            ->whereNotNull('cash_left');

        $last = ($user ? (clone $closed)->where('user_id', $user->id)->latest('closed_at')->first() : null)
            ?? $closed->latest('closed_at')->first();

        return round((float) ($last?->cash_left ?? 0), 2);
    }

    public function open(User $user, int $clinicId, float $openingAmount, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($user, $clinicId, $openingAmount, $notes) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->closeStaleSessions($clinicId);

            $existing = $this->query()
                ->where('clinic_id', $clinicId)
                ->where('user_id', $user->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'opening_amount' => 'Você já tem o caixa '.$existing->code.' aberto.',
                ]);
            }

            $session = new CashSession([
                'user_id' => $user->id,
                'code' => 'TMP-'.Str::random(16),
                'status' => 'open',
                'opened_at' => now(),
                'opening_amount' => round(max(0, $openingAmount), 2),
                'metadata' => filled($notes) ? ['opening_notes' => trim((string) $notes)] : null,
            ]);
            $session->clinic_id = $clinicId;
            $session->save();
            $session->update(['code' => $this->codeForId((int) $session->id)]);

            return $session;
        });
    }

    /**
     * Supply, withdrawal or expense registered by the operator. An expense
     * also becomes a paid expense in the financial module.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMovement(CashSession $session, string $type, array $data, ?User $by = null): CashSessionMovement
    {
        if (! in_array($type, CashSessionMovement::MANUAL_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Tipo de movimentação inválido.']);
        }

        return DB::transaction(function () use ($session, $type, $data, $by) {
            $session = $this->lock($session);
            $this->ensureOpen($session);

            $amount = round((float) ($data['amount'] ?? 0), 2);
            $description = trim((string) ($data['description'] ?? ''));
            $category = filled($data['category'] ?? null) ? trim((string) $data['category']) : null;

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Informe um valor maior que zero.']);
            }

            if ($type !== 'supply') {
                $available = (float) $this->summary($session)['cash']['expected'];

                if ($amount - $available > 0.009) {
                    throw ValidationException::withMessages([
                        'amount' => sprintf(
                            '%s maior que o dinheiro esperado no caixa (R$ %s).',
                            CashSessionMovement::TYPE_LABELS[$type],
                            number_format($available, 2, ',', '.')
                        ),
                    ]);
                }
            }

            $financialTransactionId = null;

            if ($type === 'expense') {
                $financialTransactionId = FinancialTransaction::query()->create([
                    'clinic_id' => $session->clinic_id,
                    'type' => 'expense',
                    'description' => $description.' (caixa '.$session->code.')',
                    'amount' => $amount,
                    'due_date' => today()->toDateString(),
                    'paid_at' => now(),
                    'status' => 'paid',
                    'payment_method' => 'cash',
                    'reference' => $session->code,
                    'notes' => $category,
                ])->id;
            }

            return $this->createMovement($session, [
                'type' => $type,
                'method' => 'cash',
                'payment_method_id' => $this->paymentMethods->defaultForKind($session->clinic_id, 'cash')?->id,
                'amount' => $amount,
                'description' => $description !== '' ? $description : CashSessionMovement::TYPE_LABELS[$type],
                'category' => $category,
                'financial_transaction_id' => $financialTransactionId,
                'created_by' => $by?->id ?? auth()->id(),
            ]);
        });
    }

    /**
     * Money given back to a customer (sale return or cancellation), taken
     * from the session of the operator who gives it back.
     */
    public function recordRefund(
        CashSession $session,
        Sale $sale,
        float $amount,
        string $kind,
        ?int $paymentMethodId = null,
        ?string $reason = null
    ): CashSessionMovement {
        $paymentMethodId ??= $this->paymentMethods->defaultForKind($session->clinic_id, $kind)?->id;

        return $this->createMovement($session, [
            'type' => 'refund',
            'method' => $kind,
            'payment_method_id' => $paymentMethodId,
            'amount' => round($amount, 2),
            'description' => 'Estorno da venda '.$sale->code.(filled($reason) ? ' — '.$reason : ''),
            'sale_id' => $sale->id,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Expected amounts of the session: the cash drawer (float, cash
     * receipts, change, supplies, withdrawals, expenses and cash refunds) and
     * one row per non-cash payment method, plus the card fees per machine.
     *
     * @return array<string, mixed>
     */
    public function summary(CashSession $session): array
    {
        // What came in stays in the session where it was received: a later
        // cancellation gives the money back as a refund in the canceller's
        // session instead of removing the receipt.
        $payments = SalePayment::query()
            ->with(['paymentMethod', 'sale'])
            ->where('cash_session_id', $session->id)
            ->where('method', '!=', CustomerBalanceService::CREDIT_METHOD)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereHas('sale', fn ($query) => $query->whereIn('status', ['completed', 'returned', 'cancelled']))
            ->orderBy('paid_at')
            ->get();
        $movements = $session->movements()->with(['creator', 'paymentMethod', 'sale'])->orderBy('occurred_at')->get();
        $change = (float) Sale::query()
            ->where('cash_session_id', $session->id)
            ->whereIn('status', ['completed', 'returned', 'cancelled'])
            ->sum('change_total');
        $feePayments = $payments->where('status', 'paid');

        $cashPayments = $payments->where('method', 'cash');
        $cashMovements = $movements->where('method', 'cash');
        $sumType = fn (Collection $items, string $type) => round((float) $items->where('type', $type)->sum('amount'), 2);

        $cash = [
            'opening' => round((float) $session->opening_amount, 2),
            'received' => round((float) $cashPayments->sum('amount'), 2),
            'change' => round($change, 2),
            'supplies' => $sumType($cashMovements, 'supply'),
            'withdrawals' => $sumType($cashMovements, 'withdrawal'),
            'expenses' => $sumType($cashMovements, 'expense'),
            'refunds' => $sumType($cashMovements, 'refund'),
            'credit_deposits' => $sumType($cashMovements, 'credit_deposit'),
            'credit_refunds' => $sumType($cashMovements, 'credit_refund'),
            'count' => $cashPayments->count(),
        ];
        $cash['expected'] = round(
            $cash['opening'] + $cash['received'] - $cash['change'] + $cash['supplies']
            - $cash['withdrawals'] - $cash['expenses'] - $cash['refunds']
            + $cash['credit_deposits'] - $cash['credit_refunds'],
            2
        );

        $otherMovements = $movements->where('method', '!=', 'cash');
        $methods = $this->methodRows(
            $payments->where('method', '!=', 'cash'),
            $otherMovements->whereIn('type', ['refund', 'credit_refund']),
            $otherMovements->where('type', 'credit_deposit')
        );
        $depositFees = $movements
            ->where('type', 'credit_deposit')
            ->filter(fn (CashSessionMovement $movement) => (float) ($movement->metadata['fee_amount'] ?? 0) > 0);
        $fees = round((float) $feePayments->sum('fee_amount') + (float) $depositFees->sum(fn (CashSessionMovement $movement) => (float) $movement->metadata['fee_amount']), 2);

        return [
            'cash' => $cash,
            'methods' => $methods,
            'fees_by_machine' => $this->feesByMachine($feePayments, $depositFees),
            'payments' => $payments,
            'movements' => $movements,
            'totals' => [
                'received' => round((float) $payments->sum('amount'), 2),
                'fees' => $fees,
                'expected' => round($cash['expected'] + (float) $methods->sum('expected'), 2),
                'sales_count' => $payments->pluck('sale_id')->unique()->count(),
            ],
        ];
    }

    /**
     * Closing by the operator: counted cash and counted totals per method.
     * Card fees become one paid expense per card machine. An automatic
     * closing (session left open from a previous day) takes the expected
     * values as counted and stays flagged for the manager review.
     *
     * @param  array<string, mixed>  $data
     */
    public function close(CashSession $session, array $data, ?User $by = null, bool $automatic = false): CashSession
    {
        return DB::transaction(function () use ($session, $data, $by, $automatic) {
            $session = $this->lock($session);
            $this->ensureOpen($session);

            $summary = $this->summary($session);
            $closedAt = $automatic
                ? $session->opened_at->copy()->endOfDay()->startOfMinute()
                : now();
            $expectedCash = (float) $summary['cash']['expected'];
            $countedCash = $automatic ? $expectedCash : round((float) ($data['counted_cash'] ?? 0), 2);
            $countedMethods = is_array($data['counted_methods'] ?? null) ? $data['counted_methods'] : [];

            $methods = $summary['methods']->map(function (array $row) use ($automatic, $countedMethods) {
                $counted = $automatic || ! array_key_exists($row['key'], $countedMethods) || $countedMethods[$row['key']] === null || $countedMethods[$row['key']] === ''
                    ? (float) $row['expected']
                    : round((float) $countedMethods[$row['key']], 2);

                return $row + [
                    'counted' => round($counted, 2),
                    'difference' => round($counted - (float) $row['expected'], 2),
                ];
            })->values();

            $cashLeft = $automatic
                ? $countedCash
                : min(max(0, round((float) ($data['cash_left'] ?? 0), 2)), max(0, $countedCash));
            $expectedTotal = round($expectedCash + (float) $methods->sum('expected'), 2);
            $countedTotal = round($countedCash + (float) $methods->sum('counted'), 2);

            $fees = collect($summary['fees_by_machine'])
                ->map(function (array $fee) use ($session, $closedAt) {
                    $transaction = FinancialTransaction::query()->create([
                        'clinic_id' => $session->clinic_id,
                        'type' => 'expense',
                        'description' => 'Taxas de cartão — '.$fee['label'].' (caixa '.$session->code.')',
                        'amount' => $fee['amount'],
                        'due_date' => $closedAt->toDateString(),
                        'paid_at' => $closedAt,
                        'status' => 'paid',
                        'reference' => $session->code,
                        'notes' => 'Taxas retidas pela maquininha nos recebimentos do caixa '.$session->code.'.',
                    ]);

                    return $fee + ['financial_transaction_id' => $transaction->id];
                })
                ->values()
                ->all();

            $metadata = $session->metadata ?? [];

            if ($automatic) {
                $metadata['auto_closed'] = true;
            }

            $session->update([
                'status' => 'closed',
                'closed_at' => $closedAt,
                'closed_by' => $automatic ? null : ($by?->id ?? auth()->id()),
                'expected_cash' => round($expectedCash, 2),
                'counted_cash' => $countedCash,
                'expected_total' => $expectedTotal,
                'counted_total' => $countedTotal,
                'difference_total' => round($countedTotal - $expectedTotal, 2),
                'cash_left' => $cashLeft,
                'closing_snapshot' => [
                    'version' => 1,
                    'cash' => $summary['cash'] + [
                        'counted' => $countedCash,
                        'difference' => round($countedCash - $expectedCash, 2),
                        'left' => $cashLeft,
                        'withdrawn' => round(max(0, $countedCash - $cashLeft), 2),
                    ],
                    'methods' => $methods->all(),
                    'fees_by_machine' => $fees,
                    'totals' => $summary['totals'],
                ],
                'closing_notes' => $automatic
                    ? 'Fechado automaticamente no fim do dia, sem conferência do operador.'
                    : (filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null),
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            return $session->refresh();
        });
    }

    /**
     * Manager review (encerramento) of a closed session.
     */
    public function review(CashSession $session, User $by, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($session, $by, $notes) {
            $session = $this->lock($session);

            if (! $session->isClosed()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Só é possível encerrar um caixa fechado pelo operador.',
                ]);
            }

            $session->update([
                'status' => 'reviewed',
                'reviewed_at' => now(),
                'reviewed_by' => $by->id,
                'review_notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            return $session->refresh();
        });
    }

    /**
     * Reopens a closed (not reviewed) session, cancelling the card fee
     * expenses posted at closing.
     */
    public function reopen(CashSession $session, User $by): CashSession
    {
        return DB::transaction(function () use ($session, $by) {
            $session = $this->lock($session);

            if (! $session->isClosed()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Só é possível reabrir um caixa fechado e ainda não encerrado.',
                ]);
            }

            $otherOpen = $this->query()
                ->where('clinic_id', $session->clinic_id)
                ->where('user_id', $session->user_id)
                ->open()
                ->whereKeyNot($session->id)
                ->first();

            if ($otherOpen) {
                throw ValidationException::withMessages([
                    'cash_session' => 'O operador já tem o caixa '.$otherOpen->code.' aberto. Feche-o antes de reabrir este.',
                ]);
            }

            $feeTransactionIds = collect($session->closing_snapshot['fees_by_machine'] ?? [])
                ->pluck('financial_transaction_id')
                ->filter()
                ->all();

            if ($feeTransactionIds !== []) {
                FinancialTransaction::query()
                    ->withoutGlobalScope('clinic_tenant')
                    ->whereIn('id', $feeTransactionIds)
                    ->update(['status' => 'cancelled', 'paid_at' => null]);
            }

            $metadata = $session->metadata ?? [];
            unset($metadata['auto_closed']);
            $metadata['reopened'][] = [
                'at' => now()->toIso8601String(),
                'by' => $by->id,
                'previous_counted_total' => (float) $session->counted_total,
            ];

            $session->update([
                'status' => 'open',
                'closed_at' => null,
                'closed_by' => null,
                'expected_cash' => 0,
                'counted_cash' => 0,
                'expected_total' => 0,
                'counted_total' => 0,
                'difference_total' => 0,
                'cash_left' => null,
                'closing_snapshot' => null,
                'closing_notes' => null,
                'metadata' => $metadata,
            ]);

            return $session->refresh();
        });
    }

    /**
     * Sessions left open from a previous day are closed at the end of that
     * day with the expected values, like the automatic closing at 23:59 of
     * SimplesVet, and wait for the manager review.
     */
    public function closeStaleSessions(int $clinicId): void
    {
        $this->query()
            ->where('clinic_id', $clinicId)
            ->open()
            ->where('opened_at', '<', today()->startOfDay())
            ->get()
            ->each(fn (CashSession $session) => $this->close($session, [], null, true));
    }

    /**
     * Label of the payment row for the closing count.
     */
    public function methodKey(?int $paymentMethodId, ?string $kind): string
    {
        return $paymentMethodId ? 'payment_method_'.$paymentMethodId : 'kind_'.($kind ?: 'other');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function methodRows(Collection $payments, Collection $refunds, ?Collection $deposits = null): Collection
    {
        $rows = collect();

        foreach ($deposits ?? collect() as $deposit) {
            /** @var CashSessionMovement $deposit */
            $key = $this->methodKey($deposit->payment_method_id, $deposit->method);
            $label = $deposit->paymentMethod?->name ?? (PaymentMethod::KIND_LABELS[$deposit->method] ?? 'Outro');
            $row = $rows->get($key, $this->emptyMethodRow($key, $deposit->paymentMethod, $deposit->method, $label));
            $row['count']++;
            $row['received'] += (float) $deposit->amount;
            $row['fees'] += (float) ($deposit->metadata['fee_amount'] ?? 0);
            $rows->put($key, $row);
        }

        foreach ($payments as $payment) {
            /** @var SalePayment $payment */
            $key = $this->methodKey($payment->payment_method_id, $payment->method);
            $row = $rows->get($key, $this->emptyMethodRow($key, $payment->paymentMethod, $payment->method, $payment->methodLabel()));
            $row['count']++;
            $row['received'] += (float) $payment->amount;
            // Card fees of cancelled payments are usually given back by the
            // machine together with the refund.
            $row['fees'] += $payment->status === 'paid' ? (float) $payment->fee_amount : 0.0;
            $rows->put($key, $row);
        }

        foreach ($refunds as $refund) {
            /** @var CashSessionMovement $refund */
            $key = $this->methodKey($refund->payment_method_id, $refund->method);
            $label = $refund->paymentMethod?->name ?? (PaymentMethod::KIND_LABELS[$refund->method] ?? 'Outro');
            $row = $rows->get($key, $this->emptyMethodRow($key, $refund->paymentMethod, $refund->method, $label));
            $row['refunds'] += (float) $refund->amount;
            $rows->put($key, $row);
        }

        return $rows
            ->map(function (array $row) {
                $row['received'] = round($row['received'], 2);
                $row['refunds'] = round($row['refunds'], 2);
                $row['fees'] = round($row['fees'], 2);
                $row['expected'] = round($row['received'] - $row['refunds'], 2);
                $row['net'] = round($row['expected'] - $row['fees'], 2);

                return $row;
            })
            ->sortBy(fn (array $row) => sprintf('%05d-%s', $row['sort_order'], $row['label']))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMethodRow(string $key, ?PaymentMethod $method, ?string $kind, string $label): array
    {
        return [
            'key' => $key,
            'payment_method_id' => $method?->id,
            'kind' => $kind,
            'kind_label' => PaymentMethod::KIND_LABELS[$kind] ?? 'Outro',
            'label' => $label,
            'acquirer' => $method?->acquirer,
            'sort_order' => (int) ($method?->sort_order ?? 9999),
            'count' => 0,
            'received' => 0.0,
            'refunds' => 0.0,
            'fees' => 0.0,
        ];
    }

    /**
     * Card fees grouped by machine (acquirer, or the method name when the
     * method has no acquirer).
     *
     * @return array<int, array{label: string, amount: float, count: int}>
     */
    private function feesByMachine(Collection $payments, ?Collection $deposits = null): array
    {
        $fees = $payments
            ->filter(fn (SalePayment $payment) => (float) $payment->fee_amount > 0)
            ->map(fn (SalePayment $payment) => [
                'label' => $payment->acquirer ?: $payment->methodLabel(),
                'amount' => (float) $payment->fee_amount,
            ])
            ->concat(($deposits ?? collect())->map(fn (CashSessionMovement $movement) => [
                'label' => $movement->paymentMethod?->acquirer ?: ($movement->paymentMethod?->name ?? 'Outro'),
                'amount' => (float) ($movement->metadata['fee_amount'] ?? 0),
            ]));

        return $fees
            ->groupBy('label')
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'amount' => round((float) $items->sum('amount'), 2),
                'count' => $items->count(),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * Customer credit received in the session (advance, or change kept as
     * credit): money in, by the method used.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordCreditDeposit(CashSession $session, float $amount, string $kind, ?int $paymentMethodId, string $description, array $attributes = []): CashSessionMovement
    {
        return $this->createMovement($session, $attributes + [
            'type' => 'credit_deposit',
            'method' => $kind,
            'payment_method_id' => $paymentMethodId,
            'amount' => round($amount, 2),
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Customer credit given back in money: money out of the session.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordCreditRefund(CashSession $session, float $amount, string $kind, ?int $paymentMethodId, string $description, array $attributes = []): CashSessionMovement
    {
        return $this->createMovement($session, $attributes + [
            'type' => 'credit_refund',
            'method' => $kind,
            'payment_method_id' => $paymentMethodId,
            'amount' => round($amount, 2),
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMovement(CashSession $session, array $attributes): CashSessionMovement
    {
        $movement = new CashSessionMovement($attributes + [
            'occurred_at' => now(),
        ]);
        $movement->clinic_id = $session->clinic_id;
        $movement->cash_session_id = $session->id;
        $movement->save();

        return $movement;
    }

    private function ensureOpen(CashSession $session): void
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'cash_session' => 'O caixa '.$session->code.' não está aberto.',
            ]);
        }
    }

    private function lock(CashSession $session): CashSession
    {
        return $this->query()->lockForUpdate()->findOrFail($session->id);
    }

    private function codeForId(int $id): string
    {
        return self::CODE_PREFIX.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function query()
    {
        return CashSession::query()->withoutGlobalScope('clinic_tenant');
    }
}
