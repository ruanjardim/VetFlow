<?php

namespace App\Modules\Sales\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Sales\Models\SaleQuote;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;
use App\Modules\Sales\Support\SaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A quote saved from the PDV cart. Stock is not validated: a quote does not
 * reserve products and the sale checks stock when it is completed.
 */
class StoreSaleQuoteRequest extends FormRequest
{
    use NormalizesSaleInput;
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        // Updating: a quote from another clinic is simply not found (404),
        // before any field validation runs.
        if ($this->route('quote') !== null) {
            SaleQuote::query()->findOrFail((int) $this->route('quote'));
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['discount_total', 'additions_total', 'delivery_fee'] as $field) {
            $data[$field] = $this->normalizeDecimalValue($data[$field] ?? null);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = $this->normalizeItemsInput($data['items'], false);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'tutor_id' => ['nullable', 'integer', $this->existsInCurrentClinic('tutors')],
            'patient_id' => ['nullable', 'integer', $this->existsInCurrentClinic('patients')],
            'sale_type' => ['nullable', 'string', Rule::in(SaleType::keys())],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'valid_until' => ['nullable', 'date'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'additions_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array'],
            'items.*.type' => ['nullable', 'string', Rule::in(['product', 'service', 'custom'])],
            'items.*.product_id' => ['nullable', 'integer', $this->existsInCurrentClinic('products')],
            'items.*.petshop_service_id' => ['nullable', 'integer', $this->existsInCurrentClinic('petshop_services')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_total' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Inclua pelo menos um item no orçamento.',
            'items.*.product_id.exists' => 'Um dos produtos informados não foi encontrado.',
            'items.*.petshop_service_id.exists' => 'Um dos serviços informados não foi encontrado.',
            'sale_type.in' => 'Informe um tipo de venda válido.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = array_filter(
                is_array($this->input('items')) ? $this->input('items') : [],
                fn ($item) => is_array($item)
                    && (float) ($item['quantity'] ?? 0) > 0
                    && (trim((string) ($item['description'] ?? '')) !== ''
                        || ! empty($item['product_id'])
                        || ! empty($item['petshop_service_id']))
            );

            if ($items === []) {
                $validator->errors()->add('items', 'Inclua pelo menos um item no orçamento.');
            }
        });
    }
}
