<?php

namespace App\Modules\PurchaseEntries\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Requests\StorePurchaseEntryRequest;
use App\Modules\PurchaseEntries\Requests\UpdatePurchaseEntryRequest;
use App\Modules\PurchaseEntries\Services\PurchaseEntryService;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\RedirectResponse;

class PurchaseEntryController extends Controller
{
    public function __construct(private readonly PurchaseEntryService $service)
    {
    }

    public function index()
    {
        return view('purchase-entries.index', [
            'purchaseEntries' => $this->service->paginate(),
        ]);
    }

    public function create()
    {
        return view('purchase-entries.create', [
            'entry' => null,
            'products' => $this->products(),
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function store(StorePurchaseEntryRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('purchase-entries.index')
            ->with('success', 'Entrada de mercadorias criada com sucesso.');
    }

    public function edit(int $id)
    {
        return view('purchase-entries.edit', [
            'entry' => $this->service->findOrFail($id),
            'products' => $this->products(),
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function update(UpdatePurchaseEntryRequest $request, int $id): RedirectResponse
    {
        $this->service->update($id, $request->validated());

        return redirect()
            ->route('purchase-entries.index')
            ->with('success', 'Entrada de mercadorias atualizada com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);

        return redirect()
            ->route('purchase-entries.index')
            ->with('success', 'Entrada de mercadorias removida com sucesso.');
    }

    private function products()
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    private function suppliers()
    {
        return Supplier::query()
            ->active()
            ->orderBy('name')
            ->get();
    }
}
