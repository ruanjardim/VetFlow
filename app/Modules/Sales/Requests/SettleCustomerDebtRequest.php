<?php

namespace App\Modules\Sales\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;
use App\Modules\Sales\Requests\Concerns\ValidatesPaymentMethods;
use App\Modules\Sales\Services\CustomerBalanceService;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Receipt that pays several open sales of a customer at once, with a clinic
 * method or the customer's credit.
 */
class SettleCustomerDebtRequest extends BaseRequest
{
    use NormalizesSaleInput;
    use ValidatesPaymentMethods;

    private ?Tutor $tutor = null;

    protected function prepareForValidation(): void
    {
        $paymentMethodId = $this->input('payment_method_id');
        $usesCredit = $paymentMethodId === CustomerBalanceService::CREDIT_METHOD;
        $payment = $usesCredit
            ? ['payment_method_id' => null, 'method' => CustomerBalanceService::CREDIT_METHOD]
            : $this->normalizePaymentMethodInput($this->clinicId(), ['payment_method_id' => $paymentMethodId]);

        $this->merge([
            'amount' => $this->normalizeDecimalValue($this->input('amount')),
            'payment_method_id' => $payment['payment_method_id'],
            'method' => $payment['method'] ?? null,
            'installments' => max(1, (int) ($this->input('installments') ?: 1)),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $this->clinicId()))
                    ->whereNull('deleted_at'),
            ],
            'method' => ['required', 'string'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor recebido.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'method.required' => 'Escolha a forma de recebimento.',
            'payment_method_id.exists' => 'Escolha uma forma de recebimento desta clínica.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('method') === CustomerBalanceService::CREDIT_METHOD) {
                return;
            }

            $payment = $this->only(['payment_method_id', 'method', 'installments', 'reference']);

            foreach ($this->paymentMethodErrors($this->resolvePaymentMethod($this->clinicId(), $payment), $payment, true) as $message) {
                $validator->errors()->add('payment_method_id', $message);
            }
        });
    }

    public function tutor(): Tutor
    {
        return $this->tutor ??= Tutor::query()->findOrFail((int) $this->route('tutor'));
    }

    private function clinicId(): ?int
    {
        return $this->tutor()->clinic_id ? (int) $this->tutor()->clinic_id : null;
    }
}
