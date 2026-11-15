<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Fiscal\Support\FiscalDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(FiscalDocumentType::values())],
        ];
    }
}
