<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Requests\CustomerCreditRequest;
use App\Modules\Sales\Requests\SettleCustomerDebtRequest;
use App\Modules\Sales\Services\CashSessionService;
use App\Modules\Sales\Services\CustomerBalanceService;
use App\Modules\Sales\Services\PaymentMethodService;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer balance (saldo do cliente): who owes from sales to be paid later
 * and who has credit, with the receipt of several sales at once, advances
 * and credit given back.
 */
class CustomerBalanceController
{
    public function __construct(private readonly CustomerBalanceService $balances) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'string', Rule::in(['all', 'debt', 'credit'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $customers = $this->balances->customersWithBalance($validated['filter'] ?? 'all', $validated['q'] ?? null);

        return view('sales.customer-balances.index', [
            'customers' => $customers,
            'filters' => $validated,
            'totals' => [
                'debt' => round((float) $customers->sum('debt'), 2),
                'credit' => round((float) $customers->sum(fn (array $row) => max(0, $row['credit'])), 2),
                'debtors' => $customers->filter(fn (array $row) => $row['debt'] > 0)->count(),
                'creditors' => $customers->filter(fn (array $row) => $row['credit'] > 0)->count(),
            ],
        ]);
    }

    public function show(Request $request, int $tutor): View
    {
        $customer = Tutor::query()->findOrFail($tutor);
        $clinicId = $customer->clinic_id ? (int) $customer->clinic_id : null;

        return view('sales.customer-balances.show', [
            'customer' => $customer,
            'summary' => $this->balances->summary($customer->id),
            'openSales' => $this->balances->openSales($customer->id),
            'ledger' => $this->balances->ledger($customer->id)->take(100),
            'paymentMethods' => app(PaymentMethodService::class)->activeForClinic($clinicId),
            'cashSession' => app(CashSessionService::class)->currentFor($request->user(), $clinicId),
        ]);
    }

    /**
     * Balance shown in the PDV when the customer is identified.
     */
    public function summary(int $tutor): JsonResponse
    {
        $customer = Tutor::query()->findOrFail($tutor);

        return response()->json($this->balances->summary($customer->id) + [
            'url' => route('sales.customer-balances.show', $customer->id),
        ]);
    }

    public function settle(SettleCustomerDebtRequest $request, int $tutor): RedirectResponse
    {
        $customer = $request->tutor();
        $validated = $request->validated();
        $settled = $this->balances->settle($customer, (float) $validated['amount'], [
            'payment_method_id' => $validated['payment_method_id'] ?? null,
            'method' => $validated['method'],
            'installments' => $validated['installments'] ?? 1,
            'reference' => $validated['reference'] ?? null,
            'paid_at' => now(),
        ]);

        return redirect()
            ->route('sales.customer-balances.show', $customer->id)
            ->with('success', 'Recebido R$ '.number_format((float) $validated['amount'], 2, ',', '.').' em '
                .count($settled).(count($settled) === 1 ? ' venda: ' : ' vendas: ')
                .collect($settled)->pluck('code')->implode(', ').'.');
    }

    public function deposit(CustomerCreditRequest $request, int $tutor): RedirectResponse
    {
        $customer = $request->tutor();
        $validated = $request->validated();
        $this->balances->deposit($customer, (float) $validated['amount'], (int) $validated['payment_method_id'], $validated['description'] ?? null, $request->user());

        return redirect()
            ->route('sales.customer-balances.show', $customer->id)
            ->with('success', 'Adiantamento de R$ '.number_format((float) $validated['amount'], 2, ',', '.').' guardado como crédito de '.$customer->name.'.');
    }

    public function refund(CustomerCreditRequest $request, int $tutor): RedirectResponse
    {
        $customer = $request->tutor();
        $validated = $request->validated();
        $this->balances->refundCredit($customer, (float) $validated['amount'], (int) $validated['payment_method_id'], $validated['description'] ?? null, $request->user());

        return redirect()
            ->route('sales.customer-balances.show', $customer->id)
            ->with('success', 'Devolvido R$ '.number_format((float) $validated['amount'], 2, ',', '.').' do crédito de '.$customer->name.'.');
    }
}
