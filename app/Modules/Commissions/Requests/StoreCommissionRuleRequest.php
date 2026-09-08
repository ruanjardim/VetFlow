<?php

namespace App\Modules\Commissions\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionRuleRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $percentage = $this->input('percentage');

        if (is_string($percentage) && str_contains($percentage, ',')) {
            $percentage = str_replace(',', '.', str_replace('.', '', trim($percentage)));
        }

        $this->merge([
            'percentage' => $percentage,
            'requires_paid' => $this->boolean('requires_paid'),
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'seller_user_id' => ['required', 'integer', $this->existsInCurrentClinic('users')],
            'name' => ['required', 'string', 'max:120'],
            'percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'basis' => ['required', 'string', Rule::in(['sold_total', 'gross_profit'])],
            'recognition' => ['required', 'string', Rule::in(['sale_date', 'receipt_date'])],
            'requires_paid' => ['required', 'boolean'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
