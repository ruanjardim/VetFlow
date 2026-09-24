<?php

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Requests\Concerns\ValidatesPaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalePaymentRequest extends FormRequest
{
    use ValidatesPaymentMethods;

    private ?Sale $sale = null;

    private bool $saleLoaded = false;

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

        $payment = $this->normalizePaymentMethodInput($this->saleClinicId(), [
            'payment_method_id' => $this->input('payment_method_id'),
            'method' => $this->input('method'),
        ]);

        $this->merge([
            'amount' => $amount,
            'payment_method_id' => $payment['payment_method_id'],
            'method' => $payment['method'],
            'installments' => max(1, (int) ($this->input('installments') ?: 1)),
        ]);
    }

    public function rules(): array
    {
        return [
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $this->saleClinicId()))
                    ->whereNull('deleted_at'),
            ],
            'method' => ['required_without:payment_method_id', 'nullable', 'string', Rule::in(['cash', 'pix', 'debit_card', 'credit_card', 'transfer', 'other'])],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('payment_method_id')) {
                return;
            }

            $payment = $this->only(['payment_method_id', 'method', 'installments', 'reference', 'transaction_reference']);
            $method = $this->resolvePaymentMethod($this->saleClinicId(), $payment);

            foreach ($this->paymentMethodErrors($method, $payment, true) as $message) {
                $validator->errors()->add('payment_method_id', $message);
            }
        });
    }

    public function messages(): array
    {
        return [
            'method.required_without' => 'Informe a forma de recebimento.',
            'method.in' => 'Informe uma forma de recebimento valida.',
            'payment_method_id.exists' => 'A forma de recebimento informada não foi encontrada neste estabelecimento.',
            'amount.required' => 'Informe o valor recebido.',
            'amount.gt' => 'O valor recebido deve ser maior que zero.',
        ];
    }

    private function saleClinicId(): ?int
    {
        if (! $this->saleLoaded) {
            $this->saleLoaded = true;
            $saleId = (int) $this->route('sale');
            $this->sale = $saleId > 0 ? Sale::query()->find($saleId) : null;
        }

        return $this->sale?->clinic_id ? (int) $this->sale->clinic_id : null;
    }
}
