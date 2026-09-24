<?php

namespace App\Modules\Sales\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Sales\Models\PaymentMethod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class SavePaymentMethodRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['fee_percent', 'installment_fee_percent'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim(str_replace('%', '', $value));

                if (str_contains($value, ',')) {
                    $value = str_replace(',', '.', str_replace('.', '', $value));
                }

                $data[$field] = $value === '' ? null : $value;
            }
        }

        if ($this->input('kind') !== 'credit_card') {
            $data['max_installments'] = 1;
            $data['installment_fee_percent'] = null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $clinicId = $this->clinicId();
        $methodId = (int) $this->route('paymentMethod');

        return [
            'clinic_id' => [
                Rule::requiredIf(fn (): bool => app(TenantContext::class)->isGlobal()),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_methods', 'name')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)->whereNull('deleted_at'))
                    ->ignore($methodId > 0 ? $methodId : null),
            ],
            'kind' => ['required', 'string', Rule::in(array_keys(PaymentMethod::KIND_LABELS))],
            'acquirer' => ['nullable', 'string', 'max:100'],
            'card_brand' => ['nullable', 'string', 'max:50'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installment_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settlement_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_installments' => ['required', 'integer', 'min:1', 'max:'.PaymentMethod::MAX_INSTALLMENTS],
            'installment_settlement' => ['nullable', 'string', Rule::in(array_keys(PaymentMethod::INSTALLMENT_SETTLEMENT_LABELS))],
            'requires_reference' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione o estabelecimento.',
            'name.required' => 'Informe o nome da forma de pagamento.',
            'name.max' => 'O nome deve ter no máximo 100 caracteres.',
            'name.unique' => 'Já existe uma forma de pagamento com este nome.',
            'kind.required' => 'Selecione o tipo.',
            'kind.in' => 'Selecione um tipo válido.',
            'fee_percent.numeric' => 'Informe a taxa em percentual, por exemplo 2,99.',
            'fee_percent.max' => 'A taxa não pode passar de 100%.',
            'installment_fee_percent.numeric' => 'Informe a taxa parcelada em percentual, por exemplo 4,99.',
            'installment_fee_percent.max' => 'A taxa parcelada não pode passar de 100%.',
            'settlement_days.required' => 'Informe em quantos dias o valor cai na conta (0 para na hora).',
            'settlement_days.max' => 'O prazo de repasse deve ser de até 365 dias.',
            'max_installments.max' => 'O máximo de parcelas é '.PaymentMethod::MAX_INSTALLMENTS.'.',
        ];
    }

    public function clinicId(): ?int
    {
        $clinicId = app(TenantContext::class)->clinicId();

        if ($clinicId !== null) {
            return $clinicId;
        }

        $informed = $this->input('clinic_id');

        return is_numeric($informed) ? (int) $informed : null;
    }
}
