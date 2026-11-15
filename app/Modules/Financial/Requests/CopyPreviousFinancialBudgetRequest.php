<?php

namespace App\Modules\Financial\Requests;

use App\Support\Tenancy\TenantContext;

class CopyPreviousFinancialBudgetRequest extends FinancialBudgetRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'competence' => ['required', 'date_format:Y-m'],
            'basis' => ['required', 'string', 'in:cash,competence'],
            'clinic_id' => [
                app(TenantContext::class)->isGlobal() ? 'required' : 'nullable',
                'integer',
                'exists:clinics,id',
            ],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_replace(parent::messages(), [
            'clinic_id.required' => 'Selecione o estabelecimento do orçamento.',
        ]);
    }
}
