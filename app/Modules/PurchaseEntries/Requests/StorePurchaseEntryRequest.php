<?php

namespace App\Modules\PurchaseEntries\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                $item['quantity'] = $this->normalizeDecimalValue($item['quantity'] ?? null);
                $item['unit_cost'] = $this->normalizeDecimalValue($item['unit_cost'] ?? null);
                $item['sale_price'] = $this->normalizeDecimalValue($item['sale_price'] ?? null);
                $item['margin_percent'] = $this->normalizeDecimalValue($item['margin_percent'] ?? null);
                $item['minimum_stock_after_entry'] = $this->normalizeDecimalValue($item['minimum_stock_after_entry'] ?? null);

                return $item;
            }, $data['items']);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'status' => ['required', 'string', Rule::in(['draft', 'received', 'cancelled'])],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_key' => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'payment_due_date' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'cancelled', 'overdue'])],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'bank_slip', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'installments_count' => ['nullable', 'integer', 'min:1', 'max:60'],
            'installment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'paid_at' => ['nullable', 'date'],

            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.margin_percent' => ['nullable', 'numeric'],
            'items.*.update_sale_price' => ['nullable', 'boolean'],
            'items.*.minimum_stock_after_entry' => ['nullable', 'numeric', 'min:0'],
            'items.*.barcode_snapshot' => ['nullable', 'string', 'max:64'],
            'items.*.supplier_sku' => ['nullable', 'string', 'max:255'],
            'items.*.intelligence_status' => ['nullable', 'string', 'max:255'],
            'items.*.intelligence_metadata' => ['nullable'],
            'items.*.lot_number' => ['nullable', 'string', 'max:255'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o status da entrada.',
            'status.in' => 'Informe um status valido para a entrada.',
            'supplier_id.exists' => 'O fornecedor informado nao foi encontrado.',
            'items.required' => 'Inclua pelo menos um produto na entrada.',
            'items.*.product_id.exists' => 'Um dos produtos informados nao foi encontrado.',
            'items.*.quantity.min' => 'A quantidade de cada item precisa ser maior que zero.',
            'items.*.expires_at.date' => 'Informe uma validade valida para o lote.',
            'payment_status.in' => 'Informe um status de pagamento valido.',
            'payment_method.in' => 'Informe uma forma de pagamento valida.',
            'installments_count.min' => 'Informe pelo menos uma parcela.',
            'installments_count.max' => 'Informe no maximo 60 parcelas.',
            'installment_interval_days.min' => 'Informe um intervalo valido entre parcelas.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->billableItems() === []) {
                $validator->errors()->add('items', 'Inclua pelo menos um produto com quantidade na entrada.');
            }
        });
    }

    private function billableItems(): array
    {
        return array_values(array_filter(
            $this->input('items', []),
            fn (array $item) => ! empty($item['product_id']) && (float) ($item['quantity'] ?? 0) > 0
        ));
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
