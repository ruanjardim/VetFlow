<?php

namespace App\Modules\Financial\Requests;

use Illuminate\Validation\Rule;

class FinancialBudgetDetailRequest extends FinancialBudgetRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'competence' => ['required', 'date_format:Y-m'],
            'basis' => ['required', 'string', 'in:cash,competence'],
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'financial_category_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_replace(parent::messages(), [
            'type.required' => 'Informe o tipo do lançamento.',
            'type.in' => 'O tipo do lançamento é inválido.',
            'financial_category_id.integer' => 'A categoria informada é inválida.',
        ]);
    }
}
