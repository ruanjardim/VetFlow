<?php

namespace App\Modules\Patients\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientClinicalAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o alerta clínico observado.',
        ];
    }
}
