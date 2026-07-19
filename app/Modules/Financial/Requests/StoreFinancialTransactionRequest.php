<?php

namespace App\Modules\Financial\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialTransactionRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'integer', $this->existsInCurrentClinic('suppliers')],
            'purchase_entry_id' => ['nullable', 'integer', $this->existsInCurrentClinic('purchase_entries')],
            'installment_number' => ['nullable', 'integer', 'min:1', 'max:60'],
            'installment_total' => ['nullable', 'integer', 'min:1', 'max:60'],
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'pending',
                    'paid',
                    'cancelled',
                    'overdue',
                ]),
            ],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'bank_slip', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Informe o tipo do lancamento financeiro.',
            'type.in' => 'Informe se o lancamento e uma entrada ou saida.',
            'description.required' => 'Informe a descricao do lancamento.',
            'amount.required' => 'Informe o valor do lancamento.',
            'amount.numeric' => 'Informe um valor valido.',
            'status.in' => 'Informe um status financeiro valido.',
            'supplier_id.exists' => 'O fornecedor informado nao foi encontrado.',
            'purchase_entry_id.exists' => 'A entrada de mercadorias informada nao foi encontrada.',
            'payment_method.in' => 'Informe uma forma de pagamento valida.',
        ];
    }
}
