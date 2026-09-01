<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counts' => ['required', 'array', 'min:1'],
            'counts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'counts.required' => 'Informe as quantidades físicas contadas.',
            'counts.*.numeric' => 'Use somente números nas quantidades físicas.',
            'counts.*.min' => 'A quantidade física não pode ser negativa.',
        ];
    }
}
