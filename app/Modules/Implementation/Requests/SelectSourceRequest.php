<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_source' => ['required', Rule::in(['csv', 'excel'])],
        ];
    }

    public function messages(): array
    {
        return [
            'data_source.required' => 'Selecione a origem dos dados.',
            'data_source.in' => 'Selecione uma origem de dados disponível.',
        ];
    }
}
