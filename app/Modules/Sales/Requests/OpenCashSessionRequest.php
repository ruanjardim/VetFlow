<?php

namespace App\Modules\Sales\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class OpenCashSessionRequest extends BaseRequest
{
    use NormalizesSaleInput;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_amount' => $this->normalizeDecimalValue($this->input('opening_amount')),
        ]);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => [
                Rule::requiredIf(fn (): bool => app(TenantContext::class)->isGlobal()),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'opening_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione a clínica do caixa.',
            'opening_amount.numeric' => 'Informe o fundo de troco em reais, por exemplo 100,00.',
            'opening_amount.min' => 'O fundo de troco não pode ser negativo.',
        ];
    }
}
