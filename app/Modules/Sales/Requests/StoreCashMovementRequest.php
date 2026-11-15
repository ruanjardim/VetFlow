<?php

namespace App\Modules\Sales\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Sales\Models\CashSessionMovement;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends BaseRequest
{
    use NormalizesSaleInput;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->normalizeDecimalValue($this->input('amount')),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(CashSessionMovement::MANUAL_TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Escolha suprimento, sangria ou despesa.',
            'type.in' => 'Escolha suprimento, sangria ou despesa.',
            'amount.required' => 'Informe o valor.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'description.required' => 'Descreva a movimentação, por exemplo "Troco do cofre" ou "Pagamento de comissão".',
        ];
    }
}
