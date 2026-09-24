<?php

namespace App\Modules\Sales\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;

class CloseCashSessionRequest extends BaseRequest
{
    use NormalizesSaleInput;

    protected function prepareForValidation(): void
    {
        $countedMethods = $this->input('counted_methods', []);

        $this->merge([
            'counted_cash' => $this->normalizeDecimalValue($this->input('counted_cash')),
            'cash_left' => $this->normalizeDecimalValue($this->input('cash_left')),
            'counted_methods' => is_array($countedMethods)
                ? array_map(fn ($value) => $this->normalizeDecimalValue($value), $countedMethods)
                : $countedMethods,
        ]);
    }

    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'counted_methods' => ['nullable', 'array'],
            'counted_methods.*' => ['nullable', 'numeric', 'max:9999999.99'],
            'cash_left' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'counted_cash.required' => 'Conte o dinheiro da gaveta e informe o valor.',
            'counted_cash.numeric' => 'Informe o dinheiro contado em reais, por exemplo 350,00.',
            'counted_methods.*.numeric' => 'Informe os totais de cada forma em reais.',
        ];
    }
}
