<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cancellation_reason' => trim((string) $this->input('cancellation_reason')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Informe a justificativa do cancelamento.',
            'cancellation_reason.min' => 'Descreva a justificativa com pelo menos 15 caracteres.',
            'cancellation_reason.max' => 'A justificativa pode ter no máximo 255 caracteres.',
        ];
    }
}
