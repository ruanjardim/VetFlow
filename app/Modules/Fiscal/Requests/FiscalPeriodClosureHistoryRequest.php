<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalPeriodClosureHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'competence' => ['nullable', 'date_format:Y-m'],
            'reviewer' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::in(['current', 'stale'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
