<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Requests\CancelInventoryCountRequest;
use App\Modules\Inventory\Requests\InventoryCountIndexRequest;
use App\Modules\Inventory\Requests\StoreInventoryCountRequest;
use App\Modules\Inventory\Requests\UpdateInventoryCountRequest;
use App\Modules\Inventory\Services\InventoryCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryCountController extends Controller
{
    public function __construct(
        private readonly InventoryCountService $service
    ) {}

    public function index(InventoryCountIndexRequest $request): View
    {
        return view('inventory-counts.index', $this->service->indexData($request->validated()));
    }

    public function create(): View
    {
        return view('inventory-counts.create', $this->service->createData());
    }

    public function store(StoreInventoryCountRequest $request): RedirectResponse
    {
        $count = $this->service->create($request->validated());

        return redirect()
            ->route('inventory-counts.show', $count->id)
            ->with('success', 'Contagem aberta. Registre as quantidades físicas antes de finalizar.');
    }

    public function show(int $inventoryCount): View
    {
        return view('inventory-counts.show', $this->service->showData($inventoryCount));
    }

    public function update(UpdateInventoryCountRequest $request, int $inventoryCount): RedirectResponse
    {
        $this->service->update($inventoryCount, $request->validated());

        return redirect()
            ->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Quantidades físicas salvas.');
    }

    public function finalize(int $inventoryCount): RedirectResponse
    {
        $this->service->finalize($inventoryCount);

        return redirect()
            ->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Contagem finalizada e estoque ajustado com rastreabilidade.');
    }

    public function cancel(CancelInventoryCountRequest $request, int $inventoryCount): RedirectResponse
    {
        $this->service->cancel($inventoryCount, $request->validated('cancellation_reason'));

        return redirect()
            ->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Contagem cancelada sem alterar o estoque.');
    }
}
