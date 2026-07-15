<?php

namespace App\Modules\ProductIntelligence\Controllers;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\ProductIntelligence\Services\GlobalProductCatalogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GlobalProductController
{
    public function __construct(private readonly GlobalProductCatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        return view('products.global-catalog', $this->catalog->indexData($request));
    }

    public function show(GlobalProduct $globalProduct)
    {
        return view('products.global-show', $this->catalog->showData($globalProduct));
    }

    public function suggestions(Request $request)
    {
        return view('products.global-suggestions', $this->catalog->suggestionsData($request));
    }

    public function updateStatus(Request $request, GlobalProduct $globalProduct)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys($this->catalog->statuses()))],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->catalog->updateStatus($globalProduct, $validated['status'], $validated['review_note'] ?? null);

        return back()
            ->with('success', 'Status do produto global atualizado.');
    }

    public function enrich(GlobalProduct $globalProduct)
    {
        $updated = $this->catalog->enrich($globalProduct);

        return back()
            ->with(
                $updated ? 'success' : 'error',
                $updated
                    ? 'Produto global enriquecido com sucesso.'
                    : 'Nao encontrei dados novos para este GTIN agora.'
            );
    }

    public function promote(Request $request, GlobalProduct $globalProduct)
    {
        $payload = $request->all();

        foreach (['sale_price', 'cost_price', 'stock_quantity', 'minimum_stock'] as $field) {
            $payload[$field] = $this->normalizeDecimal($payload[$field] ?? null);
        }

        $request->merge($payload);

        $validated = $request->validate([
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        $product = $this->catalog->promoteToLocalProduct($globalProduct, $validated);

        return redirect()
            ->route('products.edit', $product->id)
            ->with('success', 'Produto local criado a partir do Catalogo Global.');
    }

    public function syncLocal(GlobalProduct $globalProduct)
    {
        $count = $this->catalog->syncLocalProducts($globalProduct);

        return back()
            ->with('success', "{$count} produto(s) local(is) sincronizado(s) com dados globais.");
    }

    public function reviewSuggestion(Request $request, GlobalProductSuggestion $suggestion)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:PENDING,VERIFIED,CONFLICT'],
        ]);

        $this->catalog->reviewSuggestion($suggestion, $validated['status']);

        return back()
            ->with('success', 'Sugestao revisada.');
    }

    public function export(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'gtin',
                'nome',
                'marca',
                'fabricante',
                'categoria',
                'status',
                'confianca',
                'fonte',
                'ultima_consulta',
                'qualidade',
            ]);

            foreach ($this->catalog->exportRows($request) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'vetflow-catalogo-global.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return $value;
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }
}
