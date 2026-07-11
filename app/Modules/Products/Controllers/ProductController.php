<?php

namespace App\Modules\Products\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Products\Requests\StoreProductRequest;
use App\Modules\Products\Requests\UpdateProductRequest;
use App\Modules\Products\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends BaseCrudController
{
    public function __construct(ProductService $service)
    {
        $this->service = $service;
        $this->viewPath = 'products';
        $this->routeName = 'products';
        $this->viewVariable = 'products';
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, $this->storeRequest());

        $product = $this->service->create($validated);
        $scan = $product->gtin ?: $product->barcode ?: $request->input('gtin');

        if ($request->input('return_to') === 'sales') {
            return redirect()
                ->route('sales.create', ['scan' => $scan])
                ->with('success', 'Produto cadastrado com sucesso. O PDV vai buscar este EAN automaticamente.');
        }

        if ($request->input('return_to') === 'inventory') {
            return redirect()
                ->route('inventory-movements.create', ['scan' => $scan])
                ->with('success', 'Produto cadastrado com sucesso. O estoque vai buscar este EAN automaticamente.');
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Registro criado com sucesso.');
    }

    protected function storeRequest(): string
    {
        return StoreProductRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateProductRequest::class;
    }
}
