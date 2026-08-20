<?php

namespace App\Modules\MedicalRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveExamResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collected_at' => ['nullable', 'date'],
            'resulted_at' => ['nullable', 'date', 'after_or_equal:collected_at'],
            'laboratory_name' => ['nullable', 'string', 'max:160'],
            'result_summary' => ['nullable', 'string', 'max:5000'],
            'result_details' => ['nullable', 'string', 'max:30000'],
            'reference_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'resulted_at.after_or_equal' => 'A data do resultado não pode ser anterior à coleta.',
            'laboratory_name.max' => 'O nome do laboratório deve ter no máximo 160 caracteres.',
        ];
    }
}
