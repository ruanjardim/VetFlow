<?php

namespace App\Modules\Sales\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Inventory\Services\ProductLotService;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleQuote;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;
use App\Modules\Sales\Support\SaleType;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    use NormalizesSaleInput;
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        $data['discount_total'] = $this->normalizeDecimalValue($data['discount_total'] ?? null);
        $data['additions_total'] = $this->normalizeDecimalValue($data['additions_total'] ?? null);
        $data['delivery_fee'] = $this->normalizeDecimalValue($data['delivery_fee'] ?? null);

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = $this->normalizeItemsInput($data['items'], $this->boolean('pdv_checkout'));
        }

        if (isset($data['payments']) && is_array($data['payments'])) {
            $data['payments'] = array_map(function ($payment) {
                if (! is_array($payment)) {
                    return $payment;
                }

                $payment['amount'] = $this->normalizeDecimalValue($payment['amount'] ?? null);
                $payment['installments'] = max(1, (int) ($payment['installments'] ?? 1));

                return $payment;
            }, $data['payments']);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'unit_id' => ['nullable', 'integer'],
            'seller_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'tutor_id' => ['nullable', 'integer', $this->existsInCurrentClinic('tutors')],
            'patient_id' => ['nullable', 'integer', $this->existsInCurrentClinic('patients')],
            'service_order_id' => ['nullable', 'integer', $this->existsInCurrentClinic('service_orders')],
            'pet_package_id' => ['nullable', 'integer', $this->existsInCurrentClinic('pet_packages')],
            'sale_quote_id' => ['nullable', 'integer', $this->existsInCurrentClinic('sale_quotes')],
            'sale_type' => ['nullable', 'string', Rule::in(SaleType::keys())],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', Rule::in(['draft', 'completed', 'cancelled', 'returned'])],
            'sold_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:40'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'additions_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['nullable', 'array'],
            'items.*.type' => ['nullable', 'string', Rule::in(['product', 'service', 'custom'])],
            'items.*.product_id' => ['nullable', 'integer', $this->existsInCurrentClinic('products')],
            'items.*.petshop_service_id' => ['nullable', 'integer', $this->existsInCurrentClinic('petshop_services')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.original_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_total' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'other'])],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.installments' => ['nullable', 'integer', 'min:1', 'max:120'],
            'payments.*.card_brand' => ['nullable', 'string', 'max:80'],
            'payments.*.acquirer' => ['nullable', 'string', 'max:120'],
            'payments.*.paid_at' => ['nullable', 'date'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'payments.*.transaction_reference' => ['nullable', 'string', 'max:255'],
            'payments.*.status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'cancelled', 'refunded'])],
            'payments.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o status da venda.',
            'status.in' => 'Informe um status valido para a venda.',
            'service_order_id.exists' => 'A comanda informada nao foi encontrada.',
            'items.*.product_id.exists' => 'Um dos produtos informados nao foi encontrado.',
            'items.*.petshop_service_id.exists' => 'Um dos servicos informados nao foi encontrado.',
            'payments.*.method.in' => 'Informe uma forma de pagamento valida.',
            'sale_quote_id.exists' => 'O orçamento informado não foi encontrado.',
            'sale_type.in' => 'Informe um tipo de venda válido.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateQuoteConversion($validator);

            if ($this->input('status') !== 'completed') {
                return;
            }

            $items = $this->billableItems();

            if ($items === []) {
                $validator->errors()->add('items', 'Inclua pelo menos um item antes de finalizar a venda.');

                return;
            }

            if ($this->itemsTotal($items) <= 0) {
                $validator->errors()->add('items', 'Finalize apenas vendas com total maior que zero.');
            }

            foreach ($this->stockErrors($items) as $message) {
                $validator->errors()->add('items', $message);
            }

            if ($this->boolean('pdv_checkout')) {
                $total = $this->itemsTotal($items);
                $paid = 0.0;
                $cash = 0.0;
                $payments = $this->input('payments', []);

                foreach (is_array($payments) ? $payments : [] as $payment) {
                    if (! is_array($payment)) {
                        continue;
                    }

                    $amount = max(0, (float) ($payment['amount'] ?? 0));
                    if ($amount <= 0) {
                        continue;
                    }
                    if (empty($payment['method'])) {
                        $validator->errors()->add('payments', 'Informe a forma de cada pagamento.');

                        continue;
                    }
                    if (($payment['status'] ?? 'paid') !== 'paid') {
                        $validator->errors()->add('payments', 'O pagamento do PDV deve estar recebido para finalizar.');

                        continue;
                    }

                    $paid += $amount;
                    if ($payment['method'] === 'cash') {
                        $cash += $amount;
                    }
                }

                if (round($paid, 2) < round($total, 2)) {
                    $validator->errors()->add('payments', 'Receba o valor integral para finalizar no PDV.');
                }
                if (round($paid - $total, 2) > round($cash, 2)) {
                    $validator->errors()->add('payments', 'O troco não pode exceder o valor recebido em dinheiro.');
                }
            }
        });
    }

    private function billableItems(): array
    {
        $items = array_filter($this->input('items', []), fn (array $item) => $this->isBillableItem($item));

        if ($items !== []) {
            return array_values($items);
        }

        $serviceOrderId = $this->input('service_order_id');

        if (! $serviceOrderId) {
            return [];
        }

        $serviceOrder = ServiceOrder::query()
            ->with('items')
            ->find($serviceOrderId);

        if (! $serviceOrder) {
            return [];
        }

        return $serviceOrder->items
            ->map(fn ($item) => [
                'type' => $item->type,
                'product_id' => $item->product_id,
                'petshop_service_id' => $item->petshop_service_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])
            ->filter(fn (array $item) => $this->isBillableItem($item))
            ->values()
            ->all();
    }

    private function isBillableItem(array $item): bool
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $description = trim((string) ($item['description'] ?? ''));

        return $quantity > 0
            && ($description !== '' || ! empty($item['product_id']) || ! empty($item['petshop_service_id']));
    }

    private function itemsTotal(array $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = $this->unitPriceFor($item);
            $itemDiscount = (float) ($item['discount_total'] ?? 0);

            $subtotal += max(0, ($quantity * $unitPrice) - $itemDiscount);
        }

        $discount = (float) ($this->input('discount_total') ?? 0);
        $additions = (float) ($this->input('additions_total') ?? 0);
        $deliveryFee = SaleType::hasDelivery($this->input('sale_type'))
            ? max(0, (float) ($this->input('delivery_fee') ?? 0))
            : 0.0;

        return max(0, round($subtotal + $additions + $deliveryFee - $discount, 2));
    }

    /**
     * A quote can back a single non-cancelled sale, and only while it is open.
     */
    private function validateQuoteConversion(Validator $validator): void
    {
        $quoteId = (int) $this->input('sale_quote_id');

        if ($quoteId <= 0 || $validator->errors()->has('sale_quote_id')) {
            return;
        }

        $quote = SaleQuote::query()->find($quoteId);

        if (! $quote) {
            return;
        }

        $currentSaleId = (int) $this->route('sale');
        $alreadyLinked = Sale::query()
            ->where('sale_quote_id', $quote->id)
            ->where('status', '!=', 'cancelled')
            ->when($currentSaleId > 0, fn ($query) => $query->whereKeyNot($currentSaleId))
            ->exists();

        if ($alreadyLinked) {
            $validator->errors()->add('sale_quote_id', 'Este orçamento já está vinculado a outra venda.');

            return;
        }

        $linkedToCurrentSale = $currentSaleId > 0
            && (int) $quote->converted_sale_id === $currentSaleId;

        if (! $quote->isOpen() && ! $linkedToCurrentSale) {
            $validator->errors()->add('sale_quote_id', 'Este orçamento não está aberto para conversão.');
        }
    }

    private function stockErrors(array $items): array
    {
        $messages = [];
        $quantitiesByProduct = [];

        foreach ($items as $item) {
            if (($item['type'] ?? 'product') !== 'product' || empty($item['product_id'])) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0) + (float) ($item['quantity'] ?? 0);
        }

        if ($quantitiesByProduct === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', array_keys($quantitiesByProduct))
            ->get()
            ->keyBy('id');

        foreach ($quantitiesByProduct as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            $sellableQuantity = app(ProductLotService::class)->sellableQuantity($product);

            if ($sellableQuantity < $quantity) {
                $messages[] = sprintf(
                    'Estoque vendavel insuficiente para %s. Disponivel: %s.',
                    $product->name,
                    number_format((float) $sellableQuantity, 3, ',', '.')
                );
            }
        }

        return $messages;
    }

    private function unitPriceFor(array $item): float
    {
        $unitPrice = $item['unit_price'] ?? null;

        if ($unitPrice !== null && $unitPrice !== '') {
            return (float) $unitPrice;
        }

        if (($item['type'] ?? 'product') === 'product' && ! empty($item['product_id'])) {
            return (float) Product::query()->whereKey($item['product_id'])->value('sale_price');
        }

        if (($item['type'] ?? null) === 'service' && ! empty($item['petshop_service_id'])) {
            return (float) PetShopService::query()->whereKey($item['petshop_service_id'])->value('base_price');
        }

        return 0.0;
    }
}
