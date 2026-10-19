<?php

namespace App\Modules\Financial\Requests;

use App\Core\Base\BaseRequest;
use App\Modules\Financial\Services\FinancialManagerialResultService;
use Illuminate\Validation\Rule;

class FinancialBudgetRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'competence' => ['nullable', 'date_format:Y-m'],
            'basis' => ['nullable', Rule::in(array_keys(FinancialManagerialResultService::BASIS_LABELS))],
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
            'competence.date_format' => 'Informe uma competência válida.',
            'basis.in' => 'Escolha uma visão válida para o realizado.',
            'clinic_id.exists' => 'O estabelecimento selecionado não está disponível.',
        ];
    }
}
