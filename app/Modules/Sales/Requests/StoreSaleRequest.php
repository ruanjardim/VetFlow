<?php

namespace App\Modules\Sales\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Inventory\Services\ProductLotService;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
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

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                $item['quantity'] = $this->normalizeDecimalValue($item['quantity'] ?? null);
                $item['unit_price'] = $this->normalizeDecimalValue($item['unit_price'] ?? null);
                $item['original_unit_price'] = $this->normalizeDecimalValue($item['original_unit_price'] ?? null);
                $item['discount_total'] = $this->normalizeDecimalValue($item['discount_total'] ?? null);

                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $description = trim((string) ($item['description'] ?? ''));
                $hasCatalogItem = ! empty($item['product_id']) || ! empty($item['petshop_service_id']);
                $hasManualEntry = $description !== '' || $unitPrice > 0;

                if (! $hasCatalogItem && $hasManualEntry) {
                    $item['type'] = 'custom';
                    $item['product_id'] = null;
                    $item['petshop_service_id'] = null;
                }

                if ($unitPrice > 0 && $quantity <= 0) {
                    $item['quantity'] = '1';
                }

                if (($item['type'] ?? '') === 'custom' && $description === '' && $unitPrice > 0) {
                    $item['description'] = 'Item avulso';
                }

                return $item;
            }, $data['items']);
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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

        return max(0, round($subtotal + $additions - $discount, 2));
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

    private function normalizeDecimalValue(mixed $value): mixed
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
