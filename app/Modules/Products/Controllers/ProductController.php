<?php

namespace App\Modules\Products\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Requests\StoreProductRequest;
use App\Modules\Products\Requests\UpdateProductRequest;
use App\Modules\Products\Services\ProductIntelligenceAuditService;
use App\Modules\Products\Services\ProductService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProductController extends BaseCrudController
{
    public function __construct(ProductService $service)
    {
        $this->service = $service;
        $this->viewPath = 'products';
        $this->routeName = 'products';
        $this->viewVariable = 'products';
    }

    public function index()
    {
        $request = request();
        $auditService = app(ProductIntelligenceAuditService::class);

        return view('products.index', $auditService->indexData($request));
    }

    public function diagnostics(Request $request, ProductIntelligenceAuditService $auditService)
    {
        return view('products.diagnostics', $auditService->diagnosticsData($request));
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

        if ($request->input('return_to') === 'purchase') {
            return redirect()
                ->route('purchase-entries.create', ['scan' => $scan])
                ->with('success', 'Produto cadastrado com sucesso. A entrada vai buscar este EAN automaticamente.');
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Registro criado com sucesso.');
    }

    public function linkGlobal(int $product)
    {
        $productModel = Product::query()->findOrFail($product);
        $global = $this->service->linkGlobalProduct($productModel);

        return back()
            ->with(
                $global ? 'success' : 'error',
                $global
                    ? 'Produto vinculado ao Catalogo Global VetFlow.'
                    : 'Nao foi possivel vincular: confira se o EAN/GTIN esta valido.'
            );
    }

    public function enrich(int $product)
    {
        try {
            $updated = $this->service->enrichByGtin($product);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()
            ->with(
                $updated ? 'success' : 'error',
                $updated
                    ? 'Produto consultado e atualizado pela inteligencia.'
                    : 'Nao encontrei dados novos para este EAN agora.'
            );
    }

    public function syncGlobal(int $product)
    {
        $updated = $this->service->syncFromGlobalProduct($product);

        return back()
            ->with(
                $updated ? 'success' : 'error',
                $updated
                    ? 'Produto sincronizado com o Catalogo Global.'
                    : 'Nao encontrei um produto global para sincronizar.'
            );
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
