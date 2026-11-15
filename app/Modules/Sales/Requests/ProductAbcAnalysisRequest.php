<?php

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Services\ProductAbcAnalysisService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductAbcAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(array_keys(ProductAbcAnalysisService::PERIODS))],
            'class' => ['nullable', Rule::in(array_keys(ProductAbcAnalysisService::CLASSES))],
            'category' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
