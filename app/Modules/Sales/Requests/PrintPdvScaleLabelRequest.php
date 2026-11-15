<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintPdvScaleLabelRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'clinic_id' => $this->user()?->clinic_id ?? $this->input('clinic_id'),
            'weight' => $this->normalizeDecimalValue($this->input('weight')),
            'copies' => $this->input('copies', 1),
            'label_width_mm' => $this->input('label_width_mm', 60),
            'label_height_mm' => $this->input('label_height_mm', 40),
            'autoprint' => $this->boolean('autoprint'),
            'lot' => trim((string) $this->input('lot')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $clinicId = (int) $this->input('clinic_id');

        return [
            'clinic_id' => [
                'required',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true)->whereNull('deleted_at'),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('clinic_id', $clinicId)
                    ->where('active', true)
                    ->where('unit', 'kg')
                    ->whereNotNull('scale_code')
                    ->whereNull('deleted_at'),
            ],
            'weight' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999.999'],
            'copies' => ['required', 'integer', 'between:1,50'],
            'label_width_mm' => ['required', 'integer', 'between:50,100'],
            'label_height_mm' => ['required', 'integer', 'between:30,100'],
            'lot' => ['nullable', 'string', 'max:80'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'autoprint' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione o estabelecimento.',
            'clinic_id.exists' => 'O estabelecimento selecionado não está ativo.',
            'product_id.required' => 'Selecione a ração ou produto vendido por peso.',
            'product_id.exists' => 'Selecione um produto ativo em kg, com código de balança, deste estabelecimento.',
            'weight.required' => 'Informe o peso líquido da etiqueta.',
            'weight.decimal' => 'O peso pode ter no máximo três casas decimais.',
            'weight.min' => 'O peso mínimo é 0,001 kg.',
            'copies.between' => 'Imprima de 1 a 50 etiquetas por vez.',
            'label_width_mm.between' => 'A largura deve ficar entre 50 e 100 mm.',
            'label_height_mm.between' => 'A altura deve ficar entre 30 e 100 mm.',
            'expires_on.after_or_equal' => 'A validade da etiqueta não pode estar no passado.',
        ];
    }

    private function normalizeDecimalValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }
}
