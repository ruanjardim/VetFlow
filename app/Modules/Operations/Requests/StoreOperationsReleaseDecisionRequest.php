<?php

namespace App\Modules\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationsReleaseDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'held'])],
            'note' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn (): bool => $this->input('decision') === 'held'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.in' => 'Selecione uma decisão operacional válida.',
            'note.required' => 'Explique por que a release permanecerá em espera.',
        ];
    }
}
