<?php

namespace App\Modules\MedicalRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'examined_at' => ['required', 'date'],
            'chief_complaint' => ['nullable', 'string'],
            'anamnesis' => ['nullable', 'string'],
            'clinical_findings' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'treatment_plan' => ['nullable', 'string'],
            'prescription_notes' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'temperature' => ['nullable', 'numeric', 'between:20,50'],
            'heart_rate' => ['nullable', 'integer', 'between:1,400'],
            'respiratory_rate' => ['nullable', 'integer', 'between:1,300'],
            'hydration' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
