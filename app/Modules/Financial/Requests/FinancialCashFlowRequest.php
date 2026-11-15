<?php

namespace App\Modules\Financial\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Financial\Services\FinancialCashFlowService;
use Illuminate\Validation\Rule;

class FinancialCashFlowRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', Rule::in(array_keys(FinancialCashFlowService::HORIZON_LABELS))],
            'include_overdue' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'clinic_id' => [
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Informe uma data inicial válida.',
            'days.in' => 'Escolha um horizonte de projeção válido.',
            'include_overdue.boolean' => 'Escolha como tratar os lançamentos vencidos.',
            'date.date_format' => 'Informe uma data de composição válida.',
            'clinic_id.exists' => 'O estabelecimento selecionado não está disponível.',
        ];
    }
}
