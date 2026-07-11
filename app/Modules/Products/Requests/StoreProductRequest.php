<?php

namespace App\Modules\Products\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cost_price' => $this->normalizeDecimalValue($this->input('cost_price')),
            'sale_price' => $this->normalizeDecimalValue($this->input('sale_price')),
            'stock_quantity' => $this->normalizeDecimalValue($this->input('stock_quantity')),
            'minimum_stock' => $this->normalizeDecimalValue($this->input('minimum_stock')),
        ]);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'gtin' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'weight' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'lookup_source' => ['nullable', 'string', 'max:255'],
            'lookup_metadata' => ['nullable'],
            'looked_up_at' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do produto.',
            'clinic_id.exists' => 'A clinica informada nao foi encontrada.',
            'cost_price.numeric' => 'Informe um custo valido.',
            'sale_price.numeric' => 'Informe um preco de venda valido.',
            'stock_quantity.numeric' => 'Informe uma quantidade em estoque valida.',
            'minimum_stock.numeric' => 'Informe um estoque minimo valido.',
            'image_file.image' => 'Envie uma imagem valida para o produto.',
            'image_file.max' => 'A imagem do produto pode ter no maximo 4 MB.',
        ];
    }

    private function normalizeDecimalValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return 0;
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }
}
