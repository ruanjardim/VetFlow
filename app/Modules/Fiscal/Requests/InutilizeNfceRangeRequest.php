<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InutilizeNfceRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'justification' => trim((string) $this->input('justification')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'number_start' => ['required', 'integer', 'between:1,999999999'],
            'number_end' => ['required', 'integer', 'between:1,999999999', 'gte:number_start'],
            'justification' => ['required', 'string', 'min:15', 'max:255'],
            'confirm_inutilization' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'number_start.required' => 'Informe o primeiro número da faixa.',
            'number_end.required' => 'Informe o último número da faixa.',
            'number_end.gte' => 'O último número não pode ser menor que o primeiro.',
            'justification.required' => 'Informe a justificativa da inutilização.',
            'justification.min' => 'Descreva a justificativa com pelo menos 15 caracteres.',
            'justification.max' => 'A justificativa pode ter no máximo 255 caracteres.',
            'confirm_inutilization.accepted' => 'Confirme que a faixa foi revisada e não será utilizada.',
        ];
    }
}
