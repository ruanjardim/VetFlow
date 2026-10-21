<?php

namespace App\Modules\Financial\Requests;

use App\Modules\Financial\Services\FinancialBankReconciliationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'institution' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(FinancialBankReconciliationService::ACCOUNT_TYPE_LABELS))],
            'account_number_last4' => ['nullable', 'digits:4'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
