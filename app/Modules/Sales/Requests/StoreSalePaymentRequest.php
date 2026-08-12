<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        if (is_string($amount) && str_contains($amount, ',')) {
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
        }

        $this->merge([
            'amount' => $amount,
        ]);
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:120'],
            'card_brand' => ['nullable', 'string', 'max:80'],
            'acquirer' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Informe a forma de recebimento.',
            'method.in' => 'Informe uma forma de recebimento valida.',
            'amount.required' => 'Informe o valor recebido.',
            'amount.gt' => 'O valor recebido deve ser maior que zero.',
        ];
    }
}
