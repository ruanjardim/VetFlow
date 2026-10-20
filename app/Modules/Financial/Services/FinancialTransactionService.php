<?php

namespace App\Modules\Financial\Services;

use App\Core\Base\BaseService;
use App\Modules\Financial\Contracts\FinancialTransactionRepositoryInterface;
use App\Modules\Financial\Models\FinancialCategory;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FinancialTransactionService extends BaseService
{
    public function __construct(
        FinancialTransactionRepositoryInterface $repository,
        private readonly FinancialCategoryService $categories,
        private readonly TenantContext $tenant
    ) {
        $this->repository = $repository;
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return FinancialTransaction::query()
            ->with(['supplier', 'purchaseEntry', 'sale', 'category', 'salesCommissionSettlement', 'groomingCommissionSettlement'])
            ->when($filters['purchase_entry_id'] ?? null, fn ($query, $purchaseEntryId) => $query->where('purchase_entry_id', $purchaseEntryId))
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['financial_category_id'] ?? null, fn ($query, $categoryId) => $query->where('financial_category_id', $categoryId))
            ->orderByRaw("status = 'pending' desc")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return FinancialTransaction::query()
            ->with(['supplier', 'purchaseEntry', 'sale', 'category', 'salesCommissionSettlement', 'groomingCommissionSettlement'])
            ->findOrFail($id);
    }

    public function create(array $data): Model
    {
        if (empty($data['financial_category_id'])) {
            $data['financial_category_id'] = $this->defaultCategoryId($data);
        }

        return parent::create($data);
    }

    public function update(int $id, array $data): Model
    {
        $transaction = FinancialTransaction::query()
            ->with(['sale', 'salesCommissionSettlement', 'groomingCommissionSettlement'])
            ->findOrFail($id);
        $this->ensureEditable($transaction);

        if (
            (array_key_exists('financial_category_id', $data) && empty($data['financial_category_id']))
            || (! array_key_exists('financial_category_id', $data) && isset($data['type']) && $data['type'] !== $transaction->type)
        ) {
            $data['financial_category_id'] = $this->defaultCategoryId($data, $transaction);
        }

        return parent::update($transaction->id, $data);
    }

    public function delete(int $id): bool
    {
        $transaction = FinancialTransaction::query()
            ->with(['sale', 'salesCommissionSettlement', 'groomingCommissionSettlement'])
            ->findOrFail($id);
        $this->ensureEditable($transaction);

        return parent::delete($transaction->id);
    }

    public function markAsPaid(int $id): FinancialTransaction
    {
        $transaction = FinancialTransaction::query()->with('sale')->findOrFail($id);
        $this->ensureNotManagedBySale($transaction);

        $transaction->update([
            'status' => 'paid',
            'paid_at' => $transaction->paid_at ?? now(),
        ]);

        return $transaction->refresh();
    }

    public function cancel(int $id): FinancialTransaction
    {
        $transaction = FinancialTransaction::query()
            ->with(['sale', 'salesCommissionSettlement', 'groomingCommissionSettlement'])
            ->findOrFail($id);
        $this->ensureEditable($transaction);

        $transaction->update([
            'status' => 'cancelled',
            'paid_at' => null,
        ]);

        return $transaction->refresh();
    }

    public function cashFlowSummary(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $nextWeek = today()->addDays(7);

        $incomeMonth = $this->paidTotal('income', $monthStart, $monthEnd);
        $expenseMonth = $this->paidTotal('expense', $monthStart, $monthEnd);

        return [
            'stats' => [
                'income_month' => $incomeMonth,
                'expense_month' => $expenseMonth,
                'balance_month' => $incomeMonth - $expenseMonth,
                'income_pending' => $this->pendingTotal('income'),
                'expense_pending' => $this->pendingTotal('expense'),
                'income_overdue' => $this->overdueTotal('income'),
                'expense_overdue' => $this->overdueTotal('expense'),
                'income_next_7_days' => $this->dueBetweenTotal('income', today(), $nextWeek),
                'expense_next_7_days' => $this->dueBetweenTotal('expense', today(), $nextWeek),
            ],
            'upcoming' => $this->upcomingTransactions(),
            'overdue' => $this->overdueTransactions(),
        ];
    }

    private function paidTotal(string $type, mixed $from, mixed $to): float
    {
        return (float) FinancialTransaction::query()
            ->where('type', $type)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }

    private function pendingTotal(string $type): float
    {
        return (float) FinancialTransaction::query()
            ->where('type', $type)
            ->where('status', 'pending')
            ->sum('amount');
    }

    private function overdueTotal(string $type): float
    {
        return (float) FinancialTransaction::query()
            ->where('type', $type)
            ->where(function ($query) {
                $query
                    ->where('status', 'overdue')
                    ->orWhere(function ($query) {
                        $query
                            ->where('status', 'pending')
                            ->whereDate('due_date', '<', today());
                    });
            })
            ->sum('amount');
    }

    private function dueBetweenTotal(string $type, mixed $from, mixed $to): float
    {
        return (float) FinancialTransaction::query()
            ->where('type', $type)
            ->where('status', 'pending')
            ->whereBetween('due_date', [$from, $to])
            ->sum('amount');
    }

    private function upcomingTransactions(): Collection
    {
        return FinancialTransaction::query()
            ->with(['supplier', 'purchaseEntry', 'sale'])
            ->where('status', 'pending')
            ->whereBetween('due_date', [today(), today()->addDays(15)])
            ->orderBy('due_date')
            ->limit(12)
            ->get();
    }

    private function overdueTransactions(): Collection
    {
        return FinancialTransaction::query()
            ->with(['supplier', 'purchaseEntry', 'sale'])
            ->where(function ($query) {
                $query
                    ->where('status', 'overdue')
                    ->orWhere(function ($query) {
                        $query
                            ->where('status', 'pending')
                            ->whereDate('due_date', '<', today());
                    });
            })
            ->orderBy('due_date')
            ->limit(12)
            ->get();
    }

    private function ensureNotManagedBySale(FinancialTransaction $transaction): void
    {
        if (! $transaction->sale) {
            return;
        }

        throw ValidationException::withMessages([
            'transaction' => 'Este recebimento pertence a uma venda. Registre recebimentos, devolucoes ou cancelamentos pelo PDV.',
        ]);
    }

    private function ensureEditable(FinancialTransaction $transaction): void
    {
        $this->ensureNotManagedBySale($transaction);

        if (! $transaction->salesCommissionSettlement && ! $transaction->groomingCommissionSettlement) {
            return;
        }

        throw ValidationException::withMessages([
            'transaction' => 'Esta conta a pagar pertence a um fechamento de comissões. Faça a baixa no Financeiro, sem alterar ou cancelar o valor de origem.',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function defaultCategoryId(array $data, ?FinancialTransaction $transaction = null): ?int
    {
        $clinicId = $this->tenant->clinicId()
            ?? ($data['clinic_id'] ?? null)
            ?? $transaction?->clinic_id;
        $type = $data['type'] ?? $transaction?->type ?? 'income';
        $code = $type === 'expense'
            ? FinancialCategory::CODE_OTHER_EXPENSES
            : FinancialCategory::CODE_OTHER_INCOME;

        return $this->categories->idForCode($clinicId ? (int) $clinicId : null, $code);
    }
}
