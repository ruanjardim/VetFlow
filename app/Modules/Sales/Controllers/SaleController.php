<?php

namespace App\Modules\Sales\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Services\ProductLotService;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetPackage;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Services\ProductService;
use App\Modules\Products\Support\Gtin;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleQuote;
use App\Modules\Sales\Requests\ProductAbcAnalysisRequest;
use App\Modules\Sales\Requests\StoreSalePaymentRequest;
use App\Modules\Sales\Requests\StoreSaleRequest;
use App\Modules\Sales\Requests\UpdateSaleRequest;
use App\Modules\Sales\Services\CashSessionService;
use App\Modules\Sales\Services\PaymentMethodService;
use App\Modules\Sales\Services\ProductAbcAnalysisService;
use App\Modules\Sales\Services\SaleProfitabilityService;
use App\Modules\Sales\Services\SaleQuoteService;
use App\Modules\Sales\Services\SaleService;
use App\Modules\Sales\Support\SaleType;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends BaseCrudController
{
    public function __construct(SaleService $service)
    {
        $this->service = $service;
        $this->viewPath = 'sales';
        $this->routeName = 'sales';
        $this->viewVariable = 'sales';
    }

    public function index()
    {
        if (request()->query('status') !== 'draft') {
            return parent::index();
        }

        return view('sales.index', [
            'sales' => Sale::query()
                ->with(['tutor', 'patient', 'serviceOrder'])
                ->where('status', 'draft')
                ->latest('sold_at')
                ->paginate(15),
        ]);
    }

    public function create()
    {
        $mode = request()->query('mode');

        if ($mode === 'advanced') {
            $formData = $this->formData();

            return view('sales.create-advanced', array_merge($formData, [
                'saleTypes' => SaleType::LABELS,
                'paymentMethodOptions' => $this->paymentMethodsFor($formData['clinics'], true),
            ]));
        }

        $quoteMode = $mode === 'quote';
        $formData = $this->formData(false);
        $cashClinicIds = $this->sellingClinicIds($formData['clinics']);
        $cashSessions = app(CashSessionService::class);

        return view('sales.create', array_merge($formData, [
            'paymentMethods' => $this->paymentMethodsFor($formData['clinics']),
            'cashSessions' => collect($cashSessions->currentForClinics(auth()->user(), $cashClinicIds))
                ->map(fn ($session) => $session->toPdvArray())
                ->all(),
            'suggestedOpenings' => collect($cashClinicIds)
                ->mapWithKeys(fn (int $clinicId) => [$clinicId => $cashSessions->suggestedOpening(auth()->user(), $clinicId)])
                ->all(),
            'serviceOrderCheckout' => $quoteMode
                ? null
                : ($this->serviceOrderCheckout() ?? $this->petPackageCheckout() ?? $this->quoteCheckout()),
            'editingQuote' => $quoteMode ? $this->editableQuote() : null,
            'startMode' => $quoteMode ? 'quote' : 'sale',
            'saleTypes' => SaleType::LABELS,
            'deliverySaleTypes' => array_values(array_filter(SaleType::keys(), fn (string $type) => SaleType::hasDelivery($type))),
            'defaultQuoteValidUntil' => today()
                ->addDays(max(1, (int) config('sales.quote_validity_days', SaleQuoteService::DEFAULT_VALIDITY_DAYS)))
                ->toDateString(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request, StoreSaleRequest::class);
        $sale = $this->service->create($validated);

        if ($request->boolean('pdv_checkout')) {
            return redirect()
                ->route($sale->status === 'completed' ? 'sales.receipt' : 'sales.edit', $sale->id)
                ->with('success', $sale->status === 'completed' ? 'Venda concluída com sucesso.' : 'Venda suspensa.');
        }

        return redirect()->route('sales.index')->with('success', 'Registro criado com sucesso.');
    }

    public function edit(int $id)
    {
        $formData = $this->formData();
        $sale = $this->service->findOrFail($id);
        $sale->loadMissing('payments.paymentMethod');

        return view("{$this->viewPath}.edit", array_merge($formData, [
            'item' => $sale,
            'saleTypes' => SaleType::LABELS,
            'paymentMethodOptions' => $this->paymentMethodsFor($formData['clinics'], true),
            'receiptPaymentMethods' => app(PaymentMethodService::class)->activeForClinic($sale->clinic_id ? (int) $sale->clinic_id : null),
            'receiptCashSession' => app(CashSessionService::class)->currentFor(auth()->user(), $sale->clinic_id ? (int) $sale->clinic_id : null),
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

    public function profitability(Request $request, SaleProfitabilityService $profitability)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string', Rule::in(array_merge(['all'], array_keys(SaleProfitabilityService::TYPE_LABELS)))],
        ]);

        return view("{$this->viewPath}.profitability", [
            'summary' => $profitability->summary(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $validated['type'] ?? 'all'
            ),
            'typeLabels' => SaleProfitabilityService::TYPE_LABELS,
        ]);
    }

    public function productAbc(ProductAbcAnalysisRequest $request, ProductAbcAnalysisService $analysis): View
    {
        return view("{$this->viewPath}.product-abc", $analysis->data($request->validated()));
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
        $sale->load(['clinic', 'tutor', 'patient', 'serviceOrder', 'quote', 'items', 'payments.paymentMethod', 'events']);

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

    public function quickSearch(Request $request, ProductLotService $lotService): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);
        $clinicId = auth()->user()?->clinic_id ?? ($validated['clinic_id'] ?? null);

        if (! $clinicId) {
            return response()->json(['items' => [], 'message' => 'Selecione uma clínica para buscar itens.'], 422);
        }

        $term = trim($validated['q']);
        $pattern = '%'.addcslashes($term, '%_\\').'%';
        $products = Product::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where(function ($query) use ($pattern) {
                $query->where('name', 'like', $pattern)
                    ->orWhere('sku', 'like', $pattern)
                    ->orWhere('barcode', 'like', $pattern)
                    ->orWhere('gtin', 'like', $pattern);
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $services = PetShopService::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where('name', 'like', $pattern)
            ->orderBy('name')
            ->limit(max(0, 12 - $products->count()))
            ->get();

        $items = $products->map(fn (Product $product) => [
            'type' => 'product',
            'product_id' => $product->id,
            'description' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'gtin' => $product->gtin,
            'unit_price' => (float) $product->sale_price,
            'stock_quantity' => $lotService->sellableQuantity($product),
            'minimum_stock' => (float) $product->minimum_stock,
        ])->concat($services->map(fn (PetShopService $service) => [
            'type' => 'service',
            'petshop_service_id' => $service->id,
            'description' => $service->name,
            'unit_price' => (float) $service->base_price,
        ]))->values();

        return response()->json(['items' => $items]);
    }

    public function lookupProduct(Request $request, string $gtin, ProductLookupService $lookupService): JsonResponse
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

        $clinicId = auth()->user()?->clinic_id ?? $request->integer('clinic_id');

        if (! $clinicId) {
            return response()->json([
                'found' => false,
                'message' => 'Selecione uma clínica antes de ler o código de barras.',
            ], 422);
        }

        $product = Product::query()
            ->active()
            ->where('clinic_id', $clinicId)
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

    public function storeQuickProduct(Request $request, ProductService $products, ProductLotService $lotService): JsonResponse
    {
        $request->merge([
            'gtin' => Gtin::normalize($request->input('gtin')),
            'sale_price' => $this->normalizeDecimal($request->input('sale_price')),
            'cost_price' => $this->normalizeDecimal($request->input('cost_price')),
            'stock_quantity' => $this->normalizeDecimal($request->input('stock_quantity')),
        ]);

        $validated = $request->validate([
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
            'gtin' => ['required', 'string', 'min:8', 'max:14'],
            'name' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'min:0.01'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'string', Rule::in(['un', 'kg', 'g', 'pct', 'cx'])],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        $clinicId = auth()->user()?->clinic_id ?? ($validated['clinic_id'] ?? null);

        if (! $clinicId) {
            return response()->json(['message' => 'Selecione uma clínica antes de cadastrar o produto.'], 422);
        }

        $variants = Gtin::variants($validated['gtin']);
        $existing = Product::query()
            ->where('clinic_id', $clinicId)
            ->where(fn ($query) => $query->whereIn('gtin', $variants)->orWhereIn('barcode', $variants))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Este código de barras já está cadastrado neste estabelecimento.',
                'item' => $this->productItem($existing, $lotService),
            ], 409);
        }

        /** @var Product $product */
        $product = $products->create([
            'clinic_id' => $clinicId,
            'gtin' => $validated['gtin'],
            'barcode' => $validated['gtin'],
            'name' => trim($validated['name']),
            'sale_price' => $validated['sale_price'],
            'cost_price' => $validated['cost_price'] ?? 0,
            'stock_quantity' => $validated['stock_quantity'],
            'minimum_stock' => 0,
            'unit' => $validated['unit'],
            'category' => $validated['category'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'lookup_source' => 'pdv_quick_registration',
            'active' => true,
        ]);

        return response()->json([
            'message' => 'Produto cadastrado e adicionado ao carrinho.',
            'item' => $this->productItem($product, $lotService),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function productItem(Product $product, ProductLotService $lotService): array
    {
        return [
            'type' => 'product',
            'product_id' => $product->id,
            'petshop_service_id' => null,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => (float) $product->sale_price,
            'gtin' => $product->gtin,
            'barcode' => $product->barcode,
            'stock_quantity' => $lotService->sellableQuantity($product),
        ];
    }

    protected function storeRequest(): string
    {
        return StoreSaleRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateSaleRequest::class;
    }

    /**
     * Prefills the quick PDV with an order coming from the Banho e Tosa board.
     *
     * @return array{order: ServiceOrder, items: array<int, array<string, mixed>>}|null
     */
    private function serviceOrderCheckout(): ?array
    {
        $serviceOrderId = (int) request()->query('service_order_id');

        if ($serviceOrderId <= 0) {
            return null;
        }

        $order = ServiceOrder::query()
            ->with(['items', 'tutor', 'patient'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->find($serviceOrderId);

        if (! $order || $order->sales()->whereNot('status', 'cancelled')->exists()) {
            return null;
        }

        return [
            'order' => $order,
            'items' => $order->items
                ->map(fn ($item) => [
                    'type' => $item->type,
                    'product_id' => $item->product_id,
                    'petshop_service_id' => $item->petshop_service_id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Prefills the quick PDV with a pet package waiting for payment.
     *
     * @return array{order: null, package: PetPackage, items: array<int, array<string, mixed>>}|null
     */
    private function petPackageCheckout(): ?array
    {
        $packageId = (int) request()->query('pet_package_id');

        if ($packageId <= 0) {
            return null;
        }

        $package = PetPackage::query()
            ->with(['patient', 'tutor'])
            ->where('status', 'pending_payment')
            ->find($packageId);

        if (! $package) {
            return null;
        }

        return [
            'order' => null,
            'package' => $package,
            'items' => [[
                'type' => 'custom',
                'description' => 'Pacote '.$package->name.' ('.$package->code.') — '.($package->patient?->name ?? 'pet'),
                'quantity' => 1,
                'unit_price' => (float) $package->price,
            ]],
        ];
    }

    /**
     * Prefills the quick PDV with an open quote being converted into a sale.
     *
     * @return array{order: null, package: null, quote: SaleQuote, items: array<int, array<string, mixed>>}|null
     */
    private function quoteCheckout(): ?array
    {
        $quote = $this->openQuoteFromQuery();

        if (! $quote) {
            return null;
        }

        return [
            'order' => null,
            'package' => null,
            'quote' => $quote,
            'items' => app(SaleQuoteService::class)->cartItems($quote),
        ];
    }

    /**
     * An open quote reopened in the PDV to be edited.
     */
    private function editableQuote(): ?SaleQuote
    {
        return $this->openQuoteFromQuery();
    }

    private function openQuoteFromQuery(): ?SaleQuote
    {
        $quoteId = (int) request()->query('quote_id');

        if ($quoteId <= 0) {
            return null;
        }

        $quote = SaleQuote::query()
            ->with(['items', 'tutor', 'patient'])
            ->where('status', 'open')
            ->find($quoteId);

        if (! $quote || $quote->sales()->where('status', '!=', 'cancelled')->exists()) {
            return null;
        }

        return $quote;
    }

    /**
     * Methods offered in the PDV and sale forms: the clinic ones for a
     * clinic user, or those of every clinic for a global user (the forms
     * filter them by the selected clinic).
     *
     * @return Collection<int, PaymentMethod>
     */
    private function paymentMethodsFor(Collection $clinics, bool $includeInactive = false): Collection
    {
        $service = app(PaymentMethodService::class);

        return collect($this->sellingClinicIds($clinics))
            ->flatMap(fn ($clinicId) => $includeInactive
                ? $service->forClinic((int) $clinicId)
                : $service->activeForClinic((int) $clinicId))
            ->values();
    }

    /**
     * Clinics the user sells for: their own, or every clinic for a global
     * user (the PDV filters by the selected clinic).
     *
     * @return array<int, int>
     */
    private function sellingClinicIds(Collection $clinics): array
    {
        $userClinicId = auth()->user()?->clinic_id;

        return $userClinicId
            ? [(int) $userClinicId]
            : $clinics->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function formData(bool $includeCatalog = true): array
    {
        $data = [
            'clinics' => Clinic::query()->orderBy('trade_name')->get(),
            'tutors' => Tutor::query()->orderBy('name')->get(),
            'patients' => Patient::query()->orderBy('name')->get(),
        ];

        if (! $includeCatalog) {
            return $data;
        }

        return array_merge($data, [
            'products' => Product::query()->active()->orderBy('name')->get(),
            'petShopServices' => PetShopService::query()->active()->orderBy('name')->get(),
            'serviceOrders' => ServiceOrder::query()
                ->with(['tutor', 'patient'])
                ->latest('opened_at')
                ->limit(100)
                ->get(),
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
