<?php

namespace App\Modules\Sales\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Sales\Requests\Concerns\NormalizesSaleInput;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Validation\Rule;

/**
 * Advance received as credit, or credit given back to the customer.
 */
class CustomerCreditRequest extends BaseRequest
{
    use NormalizesSaleInput;

    private ?Tutor $tutor = null;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->normalizeDecimalValue($this->input('amount')),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $this->tutor()->clinic_id)->where('active', true))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'payment_method_id.required' => 'Escolha a forma de pagamento.',
            'payment_method_id.exists' => 'Escolha uma forma de pagamento ativa desta clínica.',
        ];
    }

    public function tutor(): Tutor
    {
        return $this->tutor ??= Tutor::query()->findOrFail((int) $this->route('tutor'));
    }
}
