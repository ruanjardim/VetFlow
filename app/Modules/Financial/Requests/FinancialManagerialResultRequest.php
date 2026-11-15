<?php

namespace App\Modules\Financial\Requests;

use App\Modules\Financial\Services\FinancialManagerialResultService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialManagerialResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'competence' => ['nullable', 'date_format:Y-m'],
            'basis' => ['nullable', Rule::in(array_keys(FinancialManagerialResultService::BASIS_LABELS))],
        ];
    }
}
