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
        return view("{$this->viewPath}.index", [
            $this->viewVariable => $this->service->paginate(),
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
        return view("{$this->viewPath}.edit", [
            'item' => $this->service->findOrFail($id),
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
