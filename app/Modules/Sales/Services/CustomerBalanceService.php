<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Sales\Models\CustomerCreditEntry;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\Sale;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer balance (saldo do cliente): what the customer owes from sales to
 * be paid later (fiado) and the credit they have (advance, change kept as
 * credit, returns and cancellations as credit). An advance is not revenue:
 * the sale that uses the credit is.
 */
class CustomerBalanceService
{
    public const CREDIT_METHOD = 'customer_credit';

    public const CREDIT_LABEL = 'Crédito do cliente';

    public function __construct(
        private readonly CashSessionService $cashSessions,
        private readonly PaymentMethodService $paymentMethods
    ) {}

    public function creditBalance(int $tutorId): float
    {
        return round((float) CustomerCreditEntry::query()->where('tutor_id', $tutorId)->sum('amount'), 2);
    }

    /**
     * Completed sales of the customer still to be paid, oldest first. Sales
     * with returns stay out: their receipts are blocked by the return flow.
     *
     * @return Collection<int, Sale>
     */
    public function openSales(int $tutorId): Collection
    {
        return Sale::query()
            ->where('tutor_id', $tutorId)
            ->where('status', 'completed')
            ->whereIn('payment_status', ['pending', 'partial'])
            ->where(fn ($query) => $query->whereNull('return_total')->orWhere('return_total', '<=', 0))
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Sale $sale) => $this->outstanding($sale) > 0)
            ->values();
    }

    public function outstanding(Sale $sale): float
    {
        return round(max(0, (float) $sale->total - (float) $sale->paid_total), 2);
    }

    /**
     * @return array{debt: float, credit: float, open_sales: int}
     */
    public function summary(int $tutorId): array
    {
        $openSales = $this->openSales($tutorId);

        return [
            'debt' => round((float) $openSales->sum(fn (Sale $sale) => $this->outstanding($sale)), 2),
            'credit' => $this->creditBalance($tutorId),
            'open_sales' => $openSales->count(),
        ];
    }

    /**
     * Credit entries with the running balance, newest first.
     *
     * @return Collection<int, CustomerCreditEntry>
     */
    public function ledger(int $tutorId): Collection
    {
        $balance = 0.0;

        return CustomerCreditEntry::query()
            ->with(['sale', 'creator', 'paymentMethod'])
            ->where('tutor_id', $tutorId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->each(function (CustomerCreditEntry $entry) use (&$balance) {
                $balance = round($balance + (float) $entry->amount, 2);
                $entry->setAttribute('running_balance', $balance);
            })
            ->reverse()
            ->values();
    }

    /**
     * Adds a signed entry to the customer credit.
     *
     * @param array<string, mixed> $links
     */
    public function addEntry(int $tutorId, ?int $clinicId, string $type, float $amount, array $links = []): CustomerCreditEntry
    {
        $entry = new CustomerCreditEntry($links + [
            'tutor_id' => $tutorId,
            'type' => $type,
            'amount' => round($amount, 2),
            'created_by' => auth()->id(),
            'occurred_at' => now(),
        ]);
        $entry->clinic_id = $clinicId;
        $entry->save();

        return $entry;
    }

    /**
     * Throws when the customer does not have enough credit.
     */
    public function ensureCredit(int $tutorId, float $amount, string $field = 'payments'): void
    {
        $available = $this->creditBalance($tutorId);

        if (round($amount - $available, 2) > 0) {
            throw ValidationException::withMessages([
                $field => 'O cliente tem R$ '.number_format($available, 2, ',', '.').' de crédito disponível.',
            ]);
        }
    }

    /**
     * Advance (adiantamento): money received now that stays as credit until
     * a sale uses it.
     */
    public function deposit(Tutor $tutor, float $amount, int $paymentMethodId, ?string $description, User $by): CustomerCreditEntry
    {
        return DB::transaction(function () use ($tutor, $amount, $paymentMethodId, $description, $by) {
            $amount = round($amount, 2);
            $method = $this->methodFor($tutor, $paymentMethodId, 'payment_method_id');
            $session = $this->cashSessions->requireOpenSession($by, (int) $tutor->clinic_id, 'amount', 'Abra o seu caixa para receber o adiantamento.');
            $fee = round($amount * $method->feePercentFor(1) / 100, 2);
            $text = trim((string) $description) !== '' ? trim((string) $description) : 'Adiantamento';

            $movement = $this->cashSessions->recordCreditDeposit(
                $session,
                $amount,
                $method->kind,
                $method->id,
                $text.' — '.$tutor->name,
                ['metadata' => $fee > 0 ? ['fee_amount' => $fee, 'tutor_id' => $tutor->id] : ['tutor_id' => $tutor->id]]
            );

            return $this->addEntry($tutor->id, (int) $tutor->clinic_id, 'deposit', $amount, [
                'cash_session_id' => $session->id,
                'payment_method_id' => $method->id,
                'description' => $text,
                'metadata' => ['cash_session_movement_id' => $movement->id],
            ]);
        });
    }

    /**
     * Credit given back to the customer in money.
     */
    public function refundCredit(Tutor $tutor, float $amount, int $paymentMethodId, ?string $description, User $by): CustomerCreditEntry
    {
        return DB::transaction(function () use ($tutor, $amount, $paymentMethodId, $description, $by) {
            $amount = round($amount, 2);
            $this->ensureCredit($tutor->id, $amount, 'amount');
            $method = $this->methodFor($tutor, $paymentMethodId, 'payment_method_id');
            $session = $this->cashSessions->requireOpenSession($by, (int) $tutor->clinic_id, 'amount', 'Abra o seu caixa para devolver o crédito.');
            $text = trim((string) $description) !== '' ? trim((string) $description) : 'Crédito devolvido';

            $movement = $this->cashSessions->recordCreditRefund(
                $session,
                $amount,
                $method->kind,
                $method->id,
                $text.' — '.$tutor->name,
                ['metadata' => ['tutor_id' => $tutor->id]]
            );

            return $this->addEntry($tutor->id, (int) $tutor->clinic_id, 'refund', -1 * $amount, [
                'cash_session_id' => $session->id,
                'payment_method_id' => $method->id,
                'description' => $text,
                'metadata' => ['cash_session_movement_id' => $movement->id],
            ]);
        });
    }

    /**
     * Pays several open sales at once, oldest first (quitação). The payment
     * is a clinic method or the customer's credit.
     *
     * @param array<string, mixed> $payment
     * @return array<int, array{sale_id: int, code: string, amount: float}>
     */
    public function settle(Tutor $tutor, float $amount, array $payment): array
    {
        return DB::transaction(function () use ($tutor, $amount, $payment) {
            $amount = round($amount, 2);
            $openSales = $this->openSales($tutor->id);
            $debt = round((float) $openSales->sum(fn (Sale $sale) => $this->outstanding($sale)), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Informe um valor maior que zero.']);
            }

            if (round($amount - $debt, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'O cliente deve R$ '.number_format($debt, 2, ',', '.').'. Para guardar o excedente, registre um adiantamento.',
                ]);
            }

            if (($payment['method'] ?? null) === self::CREDIT_METHOD) {
                $this->ensureCredit($tutor->id, $amount, 'amount');
            }

            $sales = app(SaleService::class);
            $remaining = $amount;
            $settled = [];

            foreach ($openSales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $part = round(min($remaining, $this->outstanding($sale)), 2);
                $sales->addPayment($sale->id, array_merge($payment, ['amount' => $part]));
                $remaining = round($remaining - $part, 2);
                $settled[] = ['sale_id' => $sale->id, 'code' => $sale->code, 'amount' => $part];
            }

            return $settled;
        });
    }

    /**
     * Customers with something to pay or some credit, for the balance list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function customersWithBalance(string $filter = 'all', ?string $search = null): Collection
    {
        $debts = Sale::query()
            ->whereNotNull('tutor_id')
            ->where('status', 'completed')
            ->whereIn('payment_status', ['pending', 'partial'])
            ->where(fn ($query) => $query->whereNull('return_total')->orWhere('return_total', '<=', 0))
            ->get(['id', 'tutor_id', 'total', 'paid_total', 'sold_at'])
            ->groupBy('tutor_id')
            ->map(fn (Collection $sales) => [
                'debt' => round((float) $sales->sum(fn (Sale $sale) => $this->outstanding($sale)), 2),
                'open_sales' => $sales->filter(fn (Sale $sale) => $this->outstanding($sale) > 0)->count(),
                'oldest' => $sales->min('sold_at'),
            ]);
        $credits = CustomerCreditEntry::query()
            ->selectRaw('tutor_id, SUM(amount) as balance')
            ->groupBy('tutor_id')
            ->pluck('balance', 'tutor_id')
            ->map(fn ($balance) => round((float) $balance, 2));

        $tutorIds = $debts->filter(fn (array $debt) => $debt['debt'] > 0)->keys()
            ->merge($credits->filter(fn (float $credit) => abs($credit) >= 0.01)->keys())
            ->unique()
            ->values();

        if ($tutorIds->isEmpty()) {
            return collect();
        }

        $pattern = filled($search) ? '%'.addcslashes(trim((string) $search), '%_\\').'%' : null;

        return Tutor::query()
            ->whereIn('id', $tutorIds->all())
            ->when($pattern, fn ($query) => $query->where('name', 'like', $pattern))
            ->orderBy('name')
            ->get()
            ->map(fn (Tutor $tutor) => [
                'tutor' => $tutor,
                'debt' => $debts->get($tutor->id)['debt'] ?? 0.0,
                'open_sales' => $debts->get($tutor->id)['open_sales'] ?? 0,
                'oldest' => $debts->get($tutor->id)['oldest'] ?? null,
                'credit' => $credits->get($tutor->id, 0.0),
            ])
            ->filter(fn (array $row) => match ($filter) {
                'debt' => $row['debt'] > 0,
                'credit' => $row['credit'] > 0,
                default => $row['debt'] > 0 || abs($row['credit']) >= 0.01,
            })
            ->values();
    }

    private function methodFor(Tutor $tutor, int $paymentMethodId, string $field): PaymentMethod
    {
        $method = $this->paymentMethods->find((int) $tutor->clinic_id, $paymentMethodId);

        if (! $method || ! $method->active) {
            throw ValidationException::withMessages([$field => 'Escolha uma forma de pagamento ativa da clínica.']);
        }

        return $method;
    }
}
