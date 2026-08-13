<?php

namespace App\Modules\Sales\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Support\Gtin;
use App\Modules\Sales\Requests\StoreSalePaymentRequest;
use App\Modules\Sales\Requests\StoreSaleRequest;
use App\Modules\Sales\Requests\UpdateSaleRequest;
use App\Modules\Sales\Services\SaleService;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends BaseCrudController
{
    public function __construct(SaleService $service)
    {
        $this->service = $service;
        $this->viewPath = 'sales';
        $this->routeName = 'sales';
        $this->viewVariable = 'sales';
    }

    public function create()
    {
        return view("{$this->viewPath}.create", $this->formData());
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", array_merge($this->formData(), [
            'item' => $this->service->findOrFail($id),
        ]));
    }

    public function cashier(Request $request)
    {
        return view("{$this->viewPath}.cashier", [
            'summary' => $this->service->cashierSummary(
                $request->query('from'),
                $request->query('to')
            ),
        ]);
    }

    public function cashierClose(Request $request)
    {
        return view("{$this->viewPath}.cashier-close", [
            'summary' => $this->service->cashierSummary(
                $request->query('from'),
                $request->query('to')
            ),
        ]);
    }

    public function storeCashierClose(Request $request)
    {
        $payload = $request->all();
        $payload['counted_cash'] = $this->normalizeDecimal($payload['counted_cash'] ?? null);
        $payload['counted_total'] = $this->normalizeDecimal($payload['counted_total'] ?? null);

        if (isset($payload['counted_methods']) && is_array($payload['counted_methods'])) {
            $payload['counted_methods'] = array_map(
                fn ($value) => $this->normalizeDecimal($value),
                $payload['counted_methods']
            );
        }

        $request->merge($payload);

        $paymentMethodKeys = implode(',', array_keys(SaleService::PAYMENT_METHOD_LABELS));

        $rules = [
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'unit_id' => ['nullable', 'integer'],
            'counted_methods' => ['required_without:counted_cash', 'array:'.$paymentMethodKeys, 'min:1'],
            'counted_methods.*' => ['required', 'numeric', 'min:0'],
            'counted_cash' => ['required_without:counted_methods', 'nullable', 'numeric', 'min:0'],
            'counted_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];

        foreach (array_keys(SaleService::PAYMENT_METHOD_LABELS) as $method) {
            $rules['counted_methods.'.$method] = ['required_with:counted_methods', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

        $this->service->closeCashier($validated);

        return redirect()
            ->route('sales.cashier', [
                'from' => $validated['period_from'] ?? null,
                'to' => $validated['period_to'] ?? null,
            ])
            ->with('success', 'Caixa fechado com sucesso.');
    }

    public function receipt(int $id)
    {
        $sale = $this->service->findOrFail($id);
        $sale->load(['clinic', 'tutor', 'patient', 'serviceOrder', 'items', 'payments', 'events']);

        return view("{$this->viewPath}.receipt", [
            'sale' => $sale,
        ]);
    }

    public function storePayment(StoreSalePaymentRequest $request, int $id)
    {
        $this->service->addPayment($id, $request->validated());

        return redirect()
            ->route('sales.edit', $id)
            ->with('success', 'Recebimento registrado com sucesso.');
    }

    public function cancel(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->cancelSale($id, $validated);

        return redirect()
            ->route('sales.index')
            ->with('success', 'Venda cancelada com estoque e financeiro estornados.');
    }

    public function returnForm(int $id)
    {
        $sale = $this->service->findOrFail($id);
        $sale->load(['clinic', 'tutor', 'patient', 'serviceOrder', 'items.product', 'payments']);

        return view("{$this->viewPath}.return", [
            'sale' => $sale,
        ]);
    }

    public function storeReturn(Request $request, int $id)
    {
        $payload = $request->all();
        $payload['refund_amount'] = $this->normalizeDecimal($payload['refund_amount'] ?? null);

        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $itemId => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $payload['items'][$itemId]['quantity'] = $this->normalizeDecimal($item['quantity'] ?? null);
            }
        }

        $request->merge($payload);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'refund_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'other'])],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->returnItems($id, $validated);

        return redirect()
            ->route('sales.edit', $id)
            ->with('success', 'Devolucao registrada com estoque e estorno.');
    }

    public function destroy(int $id)
    {
        $sale = $this->service->findOrFail($id);

        if ($sale->status !== 'draft') {
            return redirect()
                ->route('sales.index')
                ->with('error', 'Venda com historico nao deve ser excluida. Use cancelar ou devolver.');
        }

        $this->service->delete($id);

        return redirect()
            ->route('sales.index')
            ->with('success', 'Rascunho removido com sucesso.');
    }

    public function lookupProduct(string $gtin, ProductLookupService $lookupService): JsonResponse
    {
        $normalized = Gtin::normalize($gtin);
        $variants = Gtin::variants($normalized);

        if (! Gtin::looksValid($normalized)) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'message' => 'Codigo de barras invalido. Informe outro EAN/GTIN.',
            ], 422);
        }

        $product = Product::query()
            ->active()
            ->where(function ($query) use ($variants) {
                $query
                    ->whereIn('gtin', $variants)
                    ->orWhereIn('barcode', $variants);
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($product) {
            $warnings = [];

            if ((float) $product->sale_price <= 0) {
                $warnings[] = 'Produto sem preco de venda cadastrado.';
            }

            if ((float) $product->stock_quantity <= 0) {
                $warnings[] = 'Produto sem estoque disponivel.';
            }

            return response()->json([
                'found' => true,
                'mode' => 'product',
                'manual_allowed' => false,
                'source' => 'vetflow_product',
                'message' => $warnings === []
                    ? 'Produto adicionado a venda.'
                    : 'Produto adicionado. Revise preco e estoque antes de finalizar.',
                'warnings' => $warnings,
                'product_edit_url' => route('products.edit', $product->id),
                'item' => [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'petshop_service_id' => null,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit_price' => (float) $product->sale_price,
                    'gtin' => $product->gtin ?: $normalized,
                    'barcode' => $product->barcode ?: $normalized,
                    'stock_quantity' => (float) $product->stock_quantity,
                ],
            ]);
        }

        $outcome = $lookupService->lookupOutcome($normalized);
        $result = $outcome->result;

        if ($outcome->unavailable()) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'lookup_status' => $outcome->status,
                'retryable' => true,
                'message' => 'Consulta externa indisponivel agora. Cadastre o produto manualmente para continuar.',
            ], 503);
        }

        if (! $result?->hasUsefulData()) {
            return response()->json([
                'found' => false,
                'manual_allowed' => true,
                'lookup_status' => $outcome->status,
                'cached' => $outcome->cached,
                'message' => 'Produto nao encontrado. Cadastre este produto para usar o codigo no PDV.',
            ]);
        }

        return response()->json([
            'found' => true,
            'mode' => 'catalog',
            'manual_allowed' => true,
            'source' => $result->source,
            'global_product_id' => $result->metadata['global_product_id'] ?? null,
            'status' => $result->metadata['status'] ?? null,
            'source_confidence' => $result->metadata['source_confidence'] ?? null,
            'message' => 'Produto reconhecido no catalogo. Cadastre para usar preco e estoque automaticos; por agora, preencha o valor da linha avulsa.',
            'item' => [
                'type' => 'custom',
                'product_id' => null,
                'petshop_service_id' => null,
                'description' => $result->name ?: ('Produto '.$normalized),
                'quantity' => 1,
                'unit_price' => 0,
                'gtin' => $result->gtin ?: $normalized,
                'barcode' => $normalized,
                'brand' => $result->brand,
                'category' => $result->category,
            ],
        ]);
    }

    protected function storeRequest(): string
    {
        return StoreSaleRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateSaleRequest::class;
    }

    private function formData(): array
    {
        return [
            'clinics' => Clinic::query()->orderBy('trade_name')->get(),
            'tutors' => Tutor::query()->orderBy('name')->get(),
            'patients' => Patient::query()->orderBy('name')->get(),
            'products' => Product::query()->active()->orderBy('name')->get(),
            'petShopServices' => PetShopService::query()->active()->orderBy('name')->get(),
            'serviceOrders' => ServiceOrder::query()
                ->with(['tutor', 'patient'])
                ->latest('opened_at')
                ->limit(100)
                ->get(),
        ];
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
