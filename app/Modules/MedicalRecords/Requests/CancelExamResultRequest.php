<?php

namespace App\Modules\MedicalRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelExamResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Informe o motivo do cancelamento.',
            'cancellation_reason.min' => 'Descreva o motivo do cancelamento com pelo menos 10 caracteres.',
        ];
    }
}
