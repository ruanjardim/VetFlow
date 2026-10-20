<?php

namespace App\Modules\Commissions\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesCommissionSettlementRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirm_settlement' => $this->boolean('confirm_settlement'),
        ]);
    }

    public function rules(): array
    {
        return [
            'seller_user_id' => ['required', 'integer', $this->existsInCurrentClinic('users')],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm_settlement' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_settlement.accepted' => 'Confirme a revisão do fechamento antes de gerar a conta a pagar.',
        ];
    }
}
