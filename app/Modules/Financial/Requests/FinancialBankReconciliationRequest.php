<?php

namespace App\Modules\Financial\Requests;

use App\Core\Base\BaseRequest;
use Illuminate\Validation\Rule;

class FinancialBankReconciliationRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => [
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'account_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'reconciled'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'clinic_id.exists' => 'O estabelecimento selecionado não está disponível.',
            'from.date_format' => 'Informe uma data inicial válida.',
            'to.date_format' => 'Informe uma data final válida.',
            'to.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'status.in' => 'Escolha uma situação válida.',
        ];
    }
}
