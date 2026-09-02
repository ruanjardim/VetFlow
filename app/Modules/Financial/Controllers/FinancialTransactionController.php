<?php

namespace App\Modules\Financial\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Financial\Requests\StoreFinancialTransactionRequest;
use App\Modules\Financial\Requests\UpdateFinancialTransactionRequest;
use App\Modules\Financial\Services\FinancialTransactionService;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\RedirectResponse;

class FinancialTransactionController extends BaseCrudController
{
    public function __construct(FinancialTransactionService $service)
    {
        $this->service = $service;
        $this->viewPath = 'financial-transactions';
        $this->routeName = 'financial-transactions';
        $this->viewVariable = 'financialTransactions';
    }

    public function index()
    {
        $filters = array_filter(
            request()->only(['purchase_entry_id', 'supplier_id', 'status', 'type']),
            fn ($value) => $value !== null && $value !== ''
        );

        return view("{$this->viewPath}.index", [
            $this->viewVariable => $this->service->paginate(filters: $filters),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view("{$this->viewPath}.create", [
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function cashFlow()
    {
        return view("{$this->viewPath}.cash-flow", $this->service->cashFlowSummary());
    }

    public function edit(int $id)
    {
        $transaction = $this->service->findOrFail($id);

        if ($transaction->sale) {
            return redirect()
                ->route('sales.edit', $transaction->sale->id)
                ->with('error', 'Este recebimento pertence a uma venda. Use o PDV para receber, devolver ou cancelar.');
        }

        return view("{$this->viewPath}.edit", [
            'item' => $transaction,
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function pay(int $id): RedirectResponse
    {
        $this->service->markAsPaid($id);

        return redirect()
            ->route('financial-transactions.index')
            ->with('success', 'Lancamento baixado como pago.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->service->cancel($id);

        return redirect()
            ->route('financial-transactions.index')
            ->with('success', 'Lancamento cancelado com sucesso.');
    }

    protected function storeRequest(): string
    {
        return StoreFinancialTransactionRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateFinancialTransactionRequest::class;
    }

    private function suppliers()
    {
        return Supplier::query()
            ->active()
            ->orderBy('name')
            ->get();
    }
}
