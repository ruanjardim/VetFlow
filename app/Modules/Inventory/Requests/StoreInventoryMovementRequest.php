<?php

namespace App\Modules\Inventory\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryMovementRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'product_id' => ['required', 'integer', $this->existsInCurrentClinic('products')],
            'type' => ['required', 'string', Rule::in(['entry', 'exit', 'adjustment', 'lot_assignment'])],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Informe o produto movimentado.',
            'product_id.exists' => 'O produto informado nao foi encontrado.',
            'type.required' => 'Informe o tipo da movimentacao.',
            'type.in' => 'Informe uma movimentacao de entrada, saida ou ajuste.',
            'quantity.required' => 'Informe a quantidade movimentada.',
            'quantity.min' => 'A quantidade precisa ser maior que zero.',
            'expires_at.date' => 'Informe uma validade valida.',
            'clinic_id.exists' => 'A clinica informada nao foi encontrada.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== 'lot_assignment') {
                return;
            }

            if (! trim((string) $this->input('lot_number'))) {
                $validator->errors()->add('lot_number', 'Informe o lote para vincular o estoque atual.');
            }

            if (! $this->input('expires_at')) {
                $validator->errors()->add('expires_at', 'Informe a validade para vincular o estoque atual.');
            }
        });
    }
}
