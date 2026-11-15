<?php

namespace App\Modules\Patients\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolvePatientClinicalAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_notes' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolution_notes.required' => 'Informe por que o alerta foi resolvido.',
            'resolution_notes.min' => 'A resolução deve ter pelo menos 10 caracteres.',
        ];
    }
}
